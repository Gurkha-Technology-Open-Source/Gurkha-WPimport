# Bundle Post Importer – PRD Review

## Summary
Automate creating WordPress posts from a zipped bundle containing content.html + meta.json + local images. Update image URLs, set RankMath SEO fields, and create a draft post with logging and a preview in the admin.

## Requirements checklist
- Zip upload via Posts → Import Bundle admin page
- Extract to temp dir; validate content.html + meta.json
- Parse HTML; find <img src> with relative paths; upload images; replace to Media URLs
- Parse JSON; map title, slug, tags, metaDescription, focusKeywords
- Create draft post with updated HTML and tags
- Save RankMath SEO fields
- Preview (title, slug, tags, content with replaced URLs)
- Log per-image success/failure and link to edit post
- Non-functional: handle missing files, validate JSON, cleanup temp, admin-only access, up to 10 MB
- Out of scope: WPBakery, external images, featured image

## Assumptions
- RankMath is installed/active; otherwise SEO fields are skipped with a notice.
- Meta JSON keys are exactly as in the example; extra keys ignored.
- HTML uses relative paths only for bundle images; no subfolders.
- Images fit standard WordPress upload constraints (mime/size).

## Open questions
- Should tags be created if they don’t exist? (Default: yes)
- Should we sanitize/limit allowed HTML? (Default: allow raw HTML as stated)
- What to do on duplicate slug? (Default: let WP uniquify; keep requested slug if available)
- Max ZIP size 10 MB: enforce via PHP ini and form validation?

## Edge cases
- Missing content.html or meta.json → fail gracefully, show errors, no post creation
- Invalid JSON → show parse error
- Image referenced but missing in ZIP → keep original src and log failure
- Duplicate filenames (case differences on Windows) → pick first; log warning
- Very large HTML → rely on WP limits

## Implementation plan
1) Admin UI (menu + page)
- Add submenu under Posts: “Import Bundle”
- Form: nonce + file input (.zip, max 10 MB) + submit

2) Controller flow
- Verify capability + nonce + file type/size
- Create temp dir under wp_upload_dir()/bundle-import/<timestamp>
- Extract ZIP (ZipArchive) into temp dir
- Validate required files exist (content.html, meta.json)
- Read and decode meta.json (json_decode + error handling)
- Load content.html

3) Media handling
- Parse HTML for <img src="..."> using DOMDocument
- For each relative src:
  - Find matching file in temp dir
  - Use wp_handle_sideload/wp_insert_attachment to upload
  - Get attachment URL and replace in HTML
  - Track per-image result in log

4) Post creation
- Prepare post array: post_title, post_name (slug), post_content (updated), post_status=draft
- Insert post with wp_insert_post
- Apply tags (wp_set_post_terms)
- RankMath fields:
  - metaDescription → update_post_meta _rank_math_description
  - focusKeywords (array) → update_post_meta rank_math_focus_keyword (comma-separated)
- Persist import log (transient or in-memory for render)

5) Preview & result screen
- Show parsed title, slug, tags
- Render sanitized preview of content (updated URLs)
- Show image log table (filename, status, message)
- Link to Edit Post

6) Cleanup
- Delete temp directory after completion; on error, keep and show path for debugging

## Minimal data contracts
- Input: ZIP file with content.html, meta.json, and images
- meta.json keys: metaTitle (string), slug (string), metaDescription (string), focusKeywords (string[]), tags (string[])
- Output: Draft post ID, image log entries

## File map in this plugin
- admin/class-gurkha-wp-import-admin.php → add menu + form + handler
- includes/class-gurkha-wp-import.php → register hooks
- includes/class-gurkha-wp-import-loader.php → unchanged
- includes/class-gurkha-wp-import-i18n.php → unchanged
- includes/partials/gurkha-wp-import-admin-display.php → form + results UI
- includes/services/class-gwi-importer.php → core import logic (new)

## Test plan (manual)
- Upload happy-path bundle → see draft post, replaced URLs, tags, RankMath meta set
- Remove an image from ZIP → preview shows log failure, content keeps original src
- Missing meta.json → error message, no post created
- Bad JSON → error message
- Duplicate slug existing post → WordPress uniquifies; request slug remains if available
- Deactivate RankMath → import works; SEO fields skipped with notice

## Next steps
- Implement importer service, admin UI, and wire hooks
- Add small README + screenshots later
