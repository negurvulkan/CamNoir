ALTER TABLE events
    ADD COLUMN IF NOT EXISTS theme_settings TEXT NULL AFTER auto_approve_photos,
    ADD COLUMN IF NOT EXISTS banner_url VARCHAR(255) NULL AFTER theme_settings;

UPDATE events
SET theme_settings = '{"background":"#050509","background_accent":"rgba(200,162,255,0.1)","card":"#0c0c12","text":"#f3f3f7","muted":"#b3b3c2","primary":"#c8a2ff","border":"rgba(255,255,255,0.06)","link":"#c8a2ff","button_primary_bg":"#c8a2ff","button_primary_text":"#0b0b11","button_secondary_bg":"rgba(255,255,255,0.1)","button_secondary_text":"#f3f3f7"}'
WHERE theme_settings IS NULL OR theme_settings = '';
