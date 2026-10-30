-- InfoLoop seed data: roles, a default location and a welcome page.

INSERT INTO roles (name, label, is_location_scoped) VALUES
    ('super_admin', 'Super admin', 0),
    ('admin', 'Administrator', 0),
    ('location_admin', 'Location admin', 1),
    ('editor', 'Editor', 1),
    ('viewer', 'Viewer', 0)
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO locations (name, slug, description, timezone)
VALUES ('Headquarters', 'headquarters', 'Default location - reception and lobby screens.', 'Europe/Paris')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO settings (`key`, `value`) VALUES
    ('branding', '{"brand_name":"InfoLoop","slogan":"Digital signage for everyone","logo_file_id":null,"primary_color":"#2563eb","secondary_color":"#0f172a","accent_color":"#f59e0b","background_color":"#ffffff","text_color":"#0f172a","font_family":"Inter, system-ui, sans-serif"}'),
    ('defaults', '{"inactivity_timeout_sec":30,"timeout_enabled":true,"default_playlist_duration_sec":15,"allowed_mime":["image/png","image/jpeg","image/svg+xml","image/webp","image/gif","video/mp4","application/pdf","application/vnd.ms-powerpoint","application/vnd.openxmlformats-officedocument.presentationml.presentation","application/msword","application/vnd.openxmlformats-officedocument.wordprocessingml.document","application/vnd.ms-excel","application/vnd.openxmlformats-officedocument.spreadsheetml.sheet","text/plain"]}'),
    ('ldap', '{"enabled":false,"host":"","port":389,"base_dn":"","bind_dn":"","bind_password":"","user_filter":"(uid={username})","group_filter":"(member={dn})","attr_username":"uid","attr_email":"mail","attr_display_name":"displayName","default_role":"viewer","allowed_group":""}')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
