/**
 * Review photo drop zone. Photos already on the review are posted back as keep_photos[] and removing one just drops
 * its hidden input. New files stay in the form's hidden file input and are uploaded when the review is saved.
 */
(function () {
    const zone = document.getElementById('review-photo-zone');
    if (!zone) {
        return;
    }
    const grid = document.getElementById('review-photo-grid');
    const input = document.getElementById('new_photos');
    const max = parseInt(zone.getAttribute('data-max'), 10) || 6;
    const types = /^image\/(jpeg|png|webp)$/;
    let files = [];

    function syncInput() {
        const transfer = new DataTransfer();
        files.forEach(function (file) { transfer.items.add(file); });
        input.files = transfer.files;
    }

    function count() {
        return grid.querySelectorAll('.review-photo-item').length;
    }

    function addFile(file) {
        if (!types.test(file.type) || count() >= max) {
            return;
        }
        files.push(file);
        const item = document.createElement('div');
        item.className = 'review-photo-item';
        const img = document.createElement('img');
        img.alt = 'New photo';
        img.src = URL.createObjectURL(file);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-icon btn-danger review-photo-remove';
        remove.title = 'Remove photo';
        remove.innerHTML = '<i class="bx bx-trash"></i>';
        remove.addEventListener('click', function (e) {
            e.stopPropagation();
            files = files.filter(function (f) { return f !== file; });
            URL.revokeObjectURL(img.src);
            item.remove();
            syncInput();
        });
        item.appendChild(img);
        item.appendChild(remove);
        grid.appendChild(item);
    }

    function choose(list) {
        Array.prototype.forEach.call(list, addFile);
        syncInput();
    }

    grid.querySelectorAll('.review-photo-remove').forEach(function (button) {
        button.addEventListener('click', function () {
            button.closest('.review-photo-item').remove();
        });
    });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (name) {
        zone.addEventListener(name, function (e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    ['dragenter', 'dragover'].forEach(function (name) {
        zone.addEventListener(name, function () { zone.classList.add('drag-active'); });
    });
    ['dragleave', 'drop'].forEach(function (name) {
        zone.addEventListener(name, function () { zone.classList.remove('drag-active'); });
    });
    zone.addEventListener('drop', function (e) { choose(e.dataTransfer.files); });
    zone.addEventListener('click', function () { input.click(); });
    input.addEventListener('click', function (e) { e.stopPropagation(); });
    input.addEventListener('change', function () {
        const picked = Array.prototype.slice.call(input.files);
        choose(picked);
    });
})();
