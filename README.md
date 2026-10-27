# gear-engine

## Exercise library

The shared exercise library lives in the `gear_engine_exercise` custom post type, registered by `modules/exercises/class-gear-engine-exercises-cpt.php`. Exercise instructions are stored in the post content, with body-area and equipment taxonomies for filtering. The editor fields store an ExorLive exercise ID, player locale, suggested dosage, and an optional source/reference URL.

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

The service caps any request at 50 records; the chat retrieves at most five relevant CPT candidates when exercise intent is detected, and the backend separately enforces a maximum of five exercises per training program. The AI selects candidate IDs, while the chat builds the returned program from canonical CPT records. Existing local JSON exercise protocols remain a fallback when no matching CPT entries exist. ExorLive player URLs are constructed from the stored numeric exercise ID and locale. The chat builds the iframe from that fixed player address and shows ExorLive attribution below the video. Raw iframe HTML and arbitrary ExorLive video URLs are not stored. Exercise descriptions should be original, and any source material should be recorded in the source/reference field.

### Bulk exercise import

The Exercise Library dashboard has an **Import Exercises** submenu with a CSV template and a preview-first importer. Required columns are `title`, `description`, and either `exorlive_id` or `external_key`; optional columns are `summary`, `area`, `equipment`, `dosage`, `video_culture`, and `source_url`. Separate multiple area/equipment values with semicolons and quote CSV fields that contain commas. The importer validates rows, flags duplicates within a file, and updates existing records by ExorLive ID or external key. New records are drafts by default; publishing requires checking the explicit publish option before preview confirmation. CSV files are limited to 5 MB and 500 rows.
