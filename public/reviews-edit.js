// Review edit page: clickable star rating and the product search box (new reviews only)
(function () {
    var stars = document.querySelectorAll('.rating-star');
    var rating = document.getElementById('rating');
    var starsBox = document.getElementById('ratingStars');

    function paint(value) {
        stars.forEach(function (star, index) {
            star.style.color = index < value ? '#ffc107' : '#e0e0e0';
        });
    }

    stars.forEach(function (star) {
        star.addEventListener('click', function () {
            rating.value = this.dataset.value;
            paint(rating.value);
        });
        star.addEventListener('mouseenter', function () {
            paint(this.dataset.value);
        });
    });

    starsBox.addEventListener('mouseleave', function () {
        paint(rating.value);
    });

    var productSearch = document.getElementById('product_search');
    if (!productSearch) return;

    productSearch.addEventListener('input', function () {
        var productId = '';
        document.querySelectorAll('#productList option').forEach(function (option) {
            if (option.value === productSearch.value) productId = option.dataset.id;
        });
        document.getElementById('product_id').value = productId;
    });
})();
