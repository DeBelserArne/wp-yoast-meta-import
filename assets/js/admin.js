/**
 * Yoast SEO Meta Importer — Admin JavaScript
 * Two-step flow: (1) Upload & map columns → (2) Preview & import.
 */
(function ($) {
    'use strict';

    const $step1 = $('#ymi-step-1');
    const $step2 = $('#ymi-step-2');
    const $spinner = $('.ymi-wrap .spinner');
    let uploadState;

    // Auto-parse on file selection — no button press needed
    $('#ymi-file').on('change', function () {
        if (this.files.length) {
            uploadState = 'parse';
            doParse();
        }
    });

    function autoSelectMap(headers) {
        const lower = headers.map(function (h) { return h.toLowerCase().trim(); });
        const urlIdx = lower.findIndex(function (h) {
            return h.includes('url') || h.includes('link') || h.includes('address') || h.includes('permalink');
        });
        if (urlIdx >= 0) $('#ymi-map-url').val(headers[urlIdx]);
        const titleIdx = lower.findIndex(function (h) {
            return h.includes('title') || h.includes('titel');
        });
        if (titleIdx >= 0) $('#ymi-map-title').val(headers[titleIdx]);
        const descIdx = lower.findIndex(function (h) {
            return h.includes('description') || h.includes('descriptie') || h.includes('desc') || h.includes('meta desc');
        });
        if (descIdx >= 0) $('#ymi-map-desc').val(headers[descIdx]);
    }

    function doParse() {
        const fileInput = $('#ymi-file')[0];
        if (!fileInput.files.length) { alert('Please select a file.'); return; }
        const formData = new FormData();
        formData.append('action', 'ymi_parse_file');
        formData.append('nonce', ymi_ajax.nonce);
        formData.append('ymi_file', fileInput.files[0]);
        $spinner.addClass('is-active');
        $('#ymi-btn-upload').prop('disabled', true);
        $.ajax({
            url: ymi_ajax.ajax_url, type: 'POST', data: formData,
            processData: false, contentType: false, dataType: 'json',
            success: function (resp) {
                $spinner.removeClass('is-active');
                $('#ymi-btn-upload').prop('disabled', false);
                if (!resp.success) { alert('Error: ' + (resp.data?.message || 'Unknown error')); return; }
                const headers = resp.data.headers;
                const $selects = $('#ymi-mapping-section select');
                $selects.empty();
                headers.forEach(function (h) { $selects.append($('<option>', { value: h, text: h })); });
                autoSelectMap(headers);
                $('#ymi-mapping-section').show();
                $('#ymi-btn-upload').text('Upload & Preview');
                uploadState = 'preview';
                $('#ymi-upload-form').data('transient_key', resp.data.transient_key);
            },
            error: function () { $spinner.removeClass('is-active'); $('#ymi-btn-upload').prop('disabled', false); alert('AJAX request failed.'); }
        });
    }

    function doPreview() {
        const mapUrl = $('#ymi-map-url').val();
        const mapTitle = $('#ymi-map-title').val();
        const mapDesc = $('#ymi-map-desc').val();
        const transientKey = $('#ymi-upload-form').data('transient_key');
        if (!mapUrl || !mapTitle || !mapDesc) { alert('Please map all three columns.'); return; }
        $spinner.addClass('is-active');
        $('#ymi-btn-upload').prop('disabled', true);
        $.ajax({
            url: ymi_ajax.ajax_url, type: 'POST',
            data: { action: 'ymi_preview', nonce: ymi_ajax.nonce, map_url: mapUrl, map_title: mapTitle, map_desc: mapDesc, transient_key: transientKey },
            dataType: 'json',
            success: function (resp) {
                $spinner.removeClass('is-active');
                $('#ymi-btn-upload').prop('disabled', false);
                if (!resp.success) { alert('Error: ' + (resp.data?.message || 'Unknown error')); return; }
                renderPreview(resp.data);
                $step1.hide(); $step2.show();
                $('html, body').animate({ scrollTop: $step2.offset().top - 50 }, 400);
            },
            error: function () { $spinner.removeClass('is-active'); $('#ymi-btn-upload').prop('disabled', false); alert('AJAX request failed.'); }
        });
    }

    function renderPreview(data) {
        const preview = data.preview;
        const $tbody = $('#ymi-preview-tbody');
        $tbody.empty();
        const foundCount = data.found_count || 0;
        const notFoundCount = data.notfound_count || 0;
        $('#ymi-preview-stats').html(
            '<span class="ymi-stat"><strong>' + preview.length + '</strong> rows total</span>' +
            '<span class="ymi-stat" style="color:#155724;"><strong>' + foundCount + '</strong> pages matched</span>' +
            (notFoundCount > 0 ? '<span class="ymi-stat" style="color:#c5221f;"><strong>' + notFoundCount + '</strong> not found</span>' : '')
        );
        preview.forEach(function (entry, i) {
            const currentTitle = entry.current_title || '';
            const currentDesc = entry.current_desc || '';
            const newTitle = entry.new_title || '';
            const newDesc = entry.new_desc || '';
            const titleChanged = currentTitle !== newTitle;
            const descChanged = currentDesc !== newDesc;
            const isFound = entry.status === 'found';
            const $row = $('<tr>');
            $row.append($('<td>').addClass('check-column').append($('<input>', { type: 'checkbox', value: i, checked: isFound })));
            let pageLabel = entry.entity_label || entry.url;
            if (!isFound) {
                pageLabel += ' <span class="ymi-badge ymi-badge-notfound">not found</span>';
            } else {
                var badgeText = entry.entity_type === 'home' ? 'home' :
                    entry.entity_type === 'ptarchive' ? (entry.post_type || 'archive') :
                        entry.entity_type === 'taxonomy' ? (entry.taxonomy || 'taxonomy') :
                            (entry.post_type || 'page');
                pageLabel += ' <span class="ymi-badge ymi-badge-found">' + badgeText + '</span>';
            }
            $row.append($('<td>').html(pageLabel));
            $row.append($('<td>').text(entry.url));
            $row.append($('<td>').addClass('ymi-cell-current').text(currentTitle || '(empty)').toggleClass('ymi-cell-empty', !currentTitle));
            $row.append($('<td>').addClass('ymi-cell-new').text(newTitle || '(empty)').toggleClass('ymi-cell-empty', !newTitle).css('font-weight', titleChanged ? '700' : ''));
            $row.append($('<td>').addClass('ymi-cell-current').text(currentDesc || '(empty)').toggleClass('ymi-cell-empty', !currentDesc));
            $row.append($('<td>').addClass('ymi-cell-new').text(newDesc || '(empty)').toggleClass('ymi-cell-empty', !newDesc).css('font-weight', descChanged ? '700' : ''));
            $tbody.append($row);
        });
        $('#ymi-import-results').data('import_key', data.import_key).hide();
    }

    $('#ymi-upload-form').on('submit', function (e) {
        e.preventDefault();
        if (uploadState === 'parse') { doParse(); } else { doPreview(); }
    });

    $('#ymi-select-all').on('change', function () {
        $('#ymi-preview-tbody input[type="checkbox"]').prop('checked', this.checked);
    });

    $('#ymi-btn-import').on('click', function () {
        const selected = [];
        $('#ymi-preview-tbody input[type="checkbox"]:checked').each(function () { selected.push(parseInt($(this).val())); });
        if (selected.length === 0) { alert('Please select at least one row to import.'); return; }
        const importKey = $('#ymi-import-results').data('import_key');
        if (!confirm('Are you sure you want to update Yoast SEO metadata for ' + selected.length + ' page(s)? This will overwrite existing values.')) { return; }
        $spinner.addClass('is-active');
        $('#ymi-btn-import').prop('disabled', true);
        $.ajax({
            url: ymi_ajax.ajax_url, type: 'POST',
            data: { action: 'ymi_import', nonce: ymi_ajax.nonce, import_key: importKey, selected: JSON.stringify(selected) },
            dataType: 'json',
            success: function (resp) {
                $spinner.removeClass('is-active');
                $('#ymi-btn-import').prop('disabled', false);
                const $results = $('#ymi-import-results').show();
                if (!resp.success) {
                    $results.removeClass('ymi-results-success').addClass('ymi-results-error')
                        .html('<strong>Error:</strong> ' + (resp.data?.message || 'Unknown error'));
                    return;
                }
                const data = resp.data;
                let html = '<strong>Import complete!</strong><br>' + data.updated + ' page(s) updated successfully.<br>';
                if (data.skipped > 0) { html += data.skipped + ' row(s) skipped (not selected).<br>'; }
                if (data.errors && data.errors.length) {
                    html += '<br><strong>Warnings:</strong><ul>';
                    data.errors.forEach(function (err) { html += '<li>' + err + '</li>'; });
                    html += '</ul>';
                }
                $results.removeClass('ymi-results-error').addClass('ymi-results-success').html(html);
                $('#ymi-btn-import').prop('disabled', true);
                $('#ymi-preview-tbody input[type="checkbox"]').prop('disabled', true);
            },
            error: function () { $spinner.removeClass('is-active'); $('#ymi-btn-import').prop('disabled', false); alert('AJAX request failed.'); }
        });
    });

    $('#ymi-btn-back').on('click', function () {
        $step2.hide(); $step1.show();
        uploadState = 'preview';
        $('html, body').animate({ scrollTop: $step1.offset().top - 50 }, 400);
    });

})(jQuery);
