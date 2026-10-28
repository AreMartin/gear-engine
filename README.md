# gear-engine

## Exercise library

The shared exercise library lives in the `gear_engine_exercise` custom post type, registered by `modules/exercises/class-gear-engine-exercises-cpt.php`. Exercise instructions are stored in the editable post content, with body-area and equipment taxonomies for filtering. The editor fields store an optional ExorLive exercise ID, player locale, supported manual video URL, suggested dosage, and source/reference URL. Manual video links must use HTTPS and an ExorLive, YouTube, or Vimeo host; a manual video URL takes precedence over the ExorLive ID. Vimeo and YouTube URLs are normalized to their official player URLs, and arbitrary hosts/iframe code are not embedded.

Feature modules should read curated entries through `Gear_Engine_Exercise_Service` rather than querying the custom post type independently. For example:

```php
$exercises = Gear_Engine_Exercise_Service::search_exercises(
	array(
		'keywords'  => 'knee squat',
		'area'      => 'kne',
		'equipment' => 'vektstang',
		'limit'     => 5,
	)
);
```

The service caps any request at 50 records; the chat retrieves at most five relevant CPT candidates when exercise intent is detected, and the backend separately enforces a maximum of five exercises per training program. The AI selects candidate IDs, while the chat builds the returned program from canonical CPT records. Existing local JSON exercise protocols remain a fallback when no matching CPT entries exist. ExorLive player URLs are constructed from the stored numeric exercise ID and locale unless a manual supported video URL is provided. The chat builds embeds from approved provider URLs and shows ExorLive attribution for ExorLive videos. Raw iframe HTML is never stored. Exercise descriptions should be original, and any source material should be recorded in the source/reference field.

### Simple setup: risk and "Good for"

The default setup needs no protocols. Rate each exercise's **Risk level** and, optionally, fill in **Good for** (comma-separated conditions such as `meniscus, kneartrose, tennisalbue`). **Exercise Library → Rate Exercises** lets you edit risk and Good for for many exercises at once, with a "Needs attention" list of what is still missing. In general mode the chat takes up to 50 low/moderate-risk exercises for the body area, ranks those whose Good for tags match the triage title/category or recent user messages first, gives the AI the top 12, and the AI picks at most five. Both columns can also be filled through the CSV (`risk`, `good_for`).

### Protocols and risk levels (advanced, optional)

Protocols are off by default. Enable them under **Settings → AI → Clinical protocols (advanced)**; this shows the Protocols screen and turns the Rate Exercises page into the full Protocol Manager.

Protocols are managed in the WP dashboard under **Exercise Library → Protocols** (no JSON files). A top-level term is a condition; child terms are phases. Each term has editorial fields: clinical summary, red flags, matching ICD-10/ICPC-2 codes, dosage/progression guidance, sources, an optional review reminder date, and a Draft/Approved status. A protocol can only be saved as Approved when codes, red flags and at least one published exercise are present; the reviewer and date are filled in automatically from the approving user. If a reminder date is set, the protocol is no longer used after that date until approved again; with no date it never expires. An exercise can belong to several protocols. Sources are never exposed to the chat.

**Exercise Library → Protocol Manager** gives the overview: a *Needs attention* box (unrated exercises, exercises in no protocol, unapproved protocols) tops the *Overview* tab, which lists every protocol and phase with status, codes, exercise count, reminder date and what is still missing for approval, and an *Exercise matrix* tab shows exercises (rows) against protocols/phases (columns) with checkboxes plus a risk selector, filterable by body area and search. The Exercises list also has Risk and Protocols columns, protocol/risk filters (including "No protocol" and "Not rated"), and Bulk Edit for protocols and risk.

Each exercise also has a clinician-set **Risk level** (low, moderate, high). Exercises without a rating are only used through an approved protocol. Developers can use `Gear_Engine_Protocols::find_approved_by_codes()` and the `protocol` and `max_risk` arguments of `Gear_Engine_Exercise_Service::search_exercises()`.

### Chat exercise selection

When the user asks for exercises, the server picks one of three modes before the AI is called; the AI can only choose among the returned candidates.

- **Protocol** – the last triage card's ICD-10/ICPC-2 codes match an approved, in-date protocol: up to five exercises tagged with it are offered, with its note and dosage. The card is titled with the protocol name.
- **General** – no protocol matches but a body area is known: up to 12 rated low/moderate-risk exercises from that area, with strict selection rules. The card is labelled as general guidance. Disable via **Settings → AI → Disable general suggestions**.
- **Blocked** – high triage severity, reported red flags in the triage, or red-flag phrases in the user's recent messages (numbness, weakness, bladder control, chest pain, fever, etc.): no exercises are offered and any exercise card is removed.

The safety gate runs on every message. Without candidates, the legacy JSON fallback and the prompt's own-knowledge behavior still apply. Each exercise response is recorded (mode, protocol, codes, exercise IDs, rationales, never the user's text) in a private **Tools → Selection Log**, capped at 500 entries.

### Bulk exercise import

CSV also accepts optional `risk` and `protocol` columns. Protocols must already exist (names or slugs, semicolon-separated); unknown ones are reported, not created, and omitting the column leaves existing protocol tags unchanged.

The Exercise Library dashboard has an **Import Exercises** submenu with a CSV template and a preview-first importer. Required columns are `title`, `description` (step-by-step instructions), and either `exorlive_id` or `external_key`; optional columns are `summary`, `area`, `equipment`, `dosage`, `video_culture`, `video_url`, and `source_url`. The optional `video_url` accepts HTTPS ExorLive, YouTube, or Vimeo links and takes precedence over `exorlive_id`; for an ExorLive link the ID is read from `ex=` when `exorlive_id` is empty, so the URL alone is enough. Separate multiple area/equipment values with semicolons and quote CSV fields that contain commas. The importer validates rows, flags duplicates within a file, and updates existing records by ExorLive ID or external key. New records are drafts by default; publishing requires checking the explicit publish option before preview confirmation. CSV files are limited to 5 MB and 500 rows.

## Triage module

`modules/triage/` is a rule-based triage quiz (separate from the AI chat) that ends in a conclusion and a printable clinical note. Embed it with `[gear_engine_triage region="rygg"]` or the **Triage Quiz** block.

Each region is one JSON file in `modules/triage/configs/{region}.json` containing the quiz `steps`, ordered declarative `rules` (first match wins, otherwise `defaultDiagnosis`) and the `diagnoses` shown in the result and clinical note. Rule conditions use `{ field, op, value }` with `eq`, `neq`, `in`, `notIn`, `includes`, `notIncludes`, `empty`, `notEmpty`, combined with `all`, `any` and `not`. Validate configs and rules with `node tests/triage/triage-configs.test.js` (it also checks that every diagnosis is reachable).

Available regions: `rygg`, `skulder`, `kne`, `fot-ankel`, `hofte`, `nakke`. `rygg` and `skulder` are ports of the original prototype (verified against the legacy functions). The others carry `"reviewStatus": "draft-unreviewed"`: their clinical content was drafted without a clinician and must be reviewed before publishing.

### Training programs on the result page

**Zero setup (automatic programs):** if a diagnosis has no matching protocol, the result links to an automatic program. It is built on the program page from published exercises tagged with the region's body area (the region slug, or the config's optional `"area"`), low and moderate risk only, with exercises whose `good_for` tags match the diagnosis text listed first (max 8). Before the exercises appear, the page asks four short questions (red-flag symptoms, pain now, daily function, time since onset) and picks a phase: 1 uses low risk exercises only, 2 uses low and moderate, 3 lists moderate risk first. A yes to the red-flag question shows no exercises. The questions apply to automatic programs only; curated protocols open directly. So importing exercises and tagging their area is enough. The link only appears when the area has exercises. Unrated or high-risk exercises are never used.

**Curated (optional):** a quiz result links to a protocol program when an approved, in-date protocol that has exercises matches the diagnosis's `icd10`/`icpc2` codes (most specific code match first). Protocols take priority over automatic programs. Set it up in the existing exercise admin: create a protocol, put the diagnosis code (for example `M75.4`) in its codes, assign exercises and approve it. A diagnosis can name a protocol explicitly with `"protocol": "<slug>"`, which overrides code matching. Diagnoses with severity `Høy` or `Kritisk` never get a program, and a manual `trainingProgram.url` in a config is kept as is.

The button opens a program page: create a page at the path under **Settings → Smertesjekk → Program page path** (default `/treningsprogram/`) containing `[gear_engine_protocol]`. With `?program=<slug>` it shows that protocol (phases, exercises, lazy-loaded video, print); without it, it lists the approved programs. The viewer lives in `modules/exercises/` and shares nothing with the AI chat. Colours shared by the quiz and program page are in `assets/css/gear-engine-tokens.css`. Test with `php tests/triage/program-link.test.php`.

### Smertesjekk section (region picker)

The triage quizzes are a separate section from the AI chat ("Digital fysio"). Create one WordPress page containing `[gear_engine_triage_picker]` and one page per region containing `[gear_engine_triage region="<slug>"]`, with the region pages' URLs at `/<section path>/<slug>/`. The section path defaults to `smertesjekk` and can be changed under **Settings → Smertesjekk**. Cards come from each config's `label`, `summary` and `order`; each quiz page shows an "Alle tester" link back to the section page (disable with `back_link="false"`). Run PHP checks with `php tests/triage/triage-php.test.php`.
