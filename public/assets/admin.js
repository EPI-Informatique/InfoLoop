/*
 * InfoLoop admin enhancements:
 *  - dashboard chart (pure canvas, no dependency)
 *  - page-type settings toggle
 *  - gallery items hidden field sync
 *  - paste-to-upload images in the page editor
 *  - media usage tooltips with links to pages/revisions + revision delete
 *  - playlist item editing
 *  - revision deletion
 */
(function () {
    'use strict';

    function csrf() {
        var input = document.querySelector('input[name="_csrf"]');
        return input ? input.value : '';
    }

    // ---- Dashboard chart ----
    var canvas = document.getElementById('views-chart');
    if (canvas && window.IL_CHART) {
        var data = window.IL_CHART;
        var ctx = canvas.getContext('2d');
        var w = canvas.width = canvas.clientWidth * 2;
        var h = canvas.height = 240;
        var max = Math.max(1, ...data.map(function (d) { return d.views; }));
        var bw = w / data.length;
        ctx.fillStyle = '#2563eb';
        data.forEach(function (d, i) {
            var bh = (d.views / max) * (h - 30);
            ctx.fillRect(i * bw + 2, h - bh - 20, bw - 4, bh);
        });
        ctx.fillStyle = '#64748b';
        ctx.font = '20px system-ui';
        data.forEach(function (d, i) {
            if (data.length <= 31 || i % Math.ceil(data.length / 10) === 0) {
                ctx.fillText(d.day.slice(5), i * bw + 2, h - 4);
            }
        });
    }

    // ---- Page type settings toggle ----
    var typeSelect = document.getElementById('page-type');
    if (typeSelect) {
        var toggle = function () {
            document.querySelectorAll('.type-settings').forEach(function (fs) {
                fs.classList.toggle('visible', fs.dataset.type === typeSelect.value);
            });
        };
        typeSelect.addEventListener('change', toggle);
        toggle();
    }

    // ---- Gallery items: split textarea lines into hidden array field ----
    var galleryText = document.querySelector('textarea[name="gallery_items_text"]');
    var galleryHidden = document.getElementById('gallery-items-hidden');
    if (galleryText && galleryHidden) {
        // Convert to multiple hidden inputs on submit
        galleryText.closest('form').addEventListener('submit', function () {
            galleryHidden.remove();
            galleryText.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean).forEach(function (url) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'gallery_items[]';
                inp.value = url;
                galleryText.closest('form').appendChild(inp);
            });
        });
    }

    // ---- TinyMCE WYSIWYG editor (vendored, version pinned in vendor/tinymce/VERSION) ----
    var TINYMCE_VERSION = '7.9.1'; // keep in sync with public/assets/vendor/tinymce/VERSION
    var editorEl = document.getElementById('body-editor');
    if (editorEl && window.tinymce) {
        tinymce.init({
            selector: '#body-editor',
            license_key: 'gpl',
            base_url: '/assets/vendor/tinymce',
            suffix: '.min',
            height: 480,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image media table code hr charmap searchreplace wordcount fullscreen preview',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | table | charmap hr | removeformat code fullscreen preview',
            images_upload_url: '/admin/pages/upload-inline',
            automatic_uploads: true,
            paste_data_images: true,
            images_upload_handler: function (blobInfo, progress) {
                return new Promise(function (resolve, reject) {
                    var fd = new FormData();
                    fd.append('file', blobInfo.blob(), blobInfo.filename() || 'paste.png');
                    fd.append('_csrf', csrf());
                    fetch('/admin/pages/upload-inline', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.ok) { resolve(data.url); } else { reject(data.error || 'Upload failed'); }
                        })
                        .catch(function () { reject('Upload failed'); });
                });
            },
            file_picker_types: 'image media',
            file_picker_callback: function (cb) {
                var input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*,video/mp4';
                input.onchange = function () {
                    var fd = new FormData();
                    fd.append('file', input.files[0]);
                    fd.append('_csrf', csrf());
                    fetch('/admin/pages/upload-inline', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.ok) { cb(data.url, { title: data.name }); }
                        });
                };
                input.click();
            },
            content_style: 'img { max-width: 100%; height: auto; }',
            branding: false
        });
    } else if (editorEl) {
        // Fallback: plain textarea with paste-to-upload (TinyMCE failed to load)
        editorEl.addEventListener('paste', function (ev) {
            var items = (ev.clipboardData || window.clipboardData).items;
            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image/') === 0) {
                    ev.preventDefault();
                    var file = items[i].getAsFile();
                    var fd = new FormData();
                    fd.append('file', file, 'paste.' + (file.type.split('/')[1] || 'png'));
                    fd.append('_csrf', csrf());
                    fetch('/admin/pages/upload-inline', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.ok) {
                                var snippet = '\n<img src="' + data.url + '" alt="">';
                                var start = editorEl.selectionStart;
                                editorEl.setRangeText(snippet, start, editorEl.selectionEnd, 'end');
                            }
                        })
                        .catch(function () {});
                }
            }
        });
    }

    // ---- Media usage tooltips ----
    document.querySelectorAll('.usage-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.mediaId;
            var tip = document.getElementById('usage-' + id);
            if (!tip) { return; }
            if (!tip.hidden && tip.dataset.loaded) { tip.hidden = true; return; }
            fetch('/admin/media/' + id + '/usages', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var html = '<strong>Used in:</strong><br>';
                    if (!data.usages || !data.usages.length) { html += 'Not referenced anywhere.<br>'; }
                    data.usages.forEach(function (u) {
                        if (u.page_id) {
                            html += '• <a href="/admin/pages/' + u.page_id + '/edit">' + escapeHtml(u.page_title || ('page ' + u.page_id)) + '</a>';
                            if (u.revision_id) {
                                html += ' <a href="/admin/pages/' + u.page_id + '/revisions/' + u.revision_id + '">rev #' + u.revision_number + '</a>';
                                html += '<span class="del-rev" data-page="' + u.page_id + '" data-rev="' + u.revision_id + '">✕</span>';
                            }
                            html += '<br>';
                        } else {
                            html += '• ' + escapeHtml(u.context) + '<br>';
                        }
                    });
                    tip.innerHTML = html;
                    tip.hidden = false;
                    tip.dataset.loaded = '1';
                    tip.querySelectorAll('.del-rev').forEach(function (x) {
                        x.addEventListener('click', function () {
                            var fd = new FormData();
                            fd.append('_csrf', csrf());
                            fetch('/admin/pages/' + x.dataset.page + '/revisions/' + x.dataset.rev + '/delete', { method: 'POST', body: fd })
                                .then(function () { btn.click(); btn.click(); });
                        });
                    });
                });
        });
    });

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ---- Playlist item duration save / remove ----
    document.querySelectorAll('#playlist-items tr').forEach(function (row) {
        var dur = row.querySelector('.dur');
        if (dur) {
            dur.addEventListener('change', function () {
                var fd = new FormData();
                fd.append('_csrf', csrf());
                fd.append('duration_sec', dur.value);
                fd.append('position', row.cells[0].textContent.trim());
                fetch('/admin/playlists/items/' + row.dataset.item, { method: 'POST', body: fd }).catch(function () {});
            });
        }
        var rm = row.querySelector('.remove-item');
        if (rm) {
            rm.addEventListener('click', function () {
                var fd = new FormData();
                fd.append('_csrf', csrf());
                fetch('/admin/playlists/items/' + row.dataset.item + '/delete', { method: 'POST', body: fd })
                    .then(function () { row.remove(); });
            });
        }
    });

    // ---- Revision delete buttons ----
    document.querySelectorAll('[data-delete-revision]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this revision permanently?')) { return; }
            var fd = new FormData();
            fd.append('_csrf', csrf());
            fetch('/admin/pages/' + btn.dataset.page + '/revisions/' + btn.dataset.deleteRevision + '/delete', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) { location.reload(); } else { alert(data.error || 'Delete failed'); }
                });
        });
    });
})();
