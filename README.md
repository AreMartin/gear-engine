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

### Protocols and risk levels

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

The Exercise Library dashboard has an **Import Exercises** submenu with a CSV template and a preview-first importer. Required columns are `title`, `description` (step-by-step instructions), and either `exorlive_id` or `external_key`; optional columns are `summary`, `area`, `equipment`, `dosage`, `video_culture`, `video_url`, and `source_url`. The optional `video_url` accepts HTTPS ExorLive, YouTube, or Vimeo links and takes precedence over `exorlive_id`. Separate multiple area/equipment values with semicolons and quote CSV fields that contain commas. The importer validates rows, flags duplicates within a file, and updates existing records by ExorLive ID or external key. New records are drafts by default; publishing requires checking the explicit publish option before preview confirmation. CSV files are limited to 5 MB and 500 rows.
