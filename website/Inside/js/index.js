(function () {
    function request(type, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "api.php", true);
        xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                callback(response);
            }
        };
        data.type = type;
        xhr.send(JSON.stringify(data));
    }

    function renderProducts(products) {
        var container = document.querySelector(".products");
        container.innerHTML = "";
        for (var i = 0; i < products.length; i++) {
            var p = products[i];
            var div = document.createElement("div");
            div.className = "product";
            div.innerHTML =
                "<h3>" + p.name + "</h3>" +
                "<img src='" + p.image + "' alt='" + p.name + "' width='150'>" +
                "<p>" + p.description + "</p>" +
                "<p><strong>R" + p.price + "</strong></p>" +
                "<p>Brand: " + p.brand + "</p>" +
                "<p>Category: " + p.category + "</p>" +
                "<p>Stockist: " + p.stockist + "</p>";
            container.appendChild(div);
        }
    }

    function loadProducts() {
        request("GetAllProducts", {}, function (res) {
            if (res.success) {
                renderProducts(res.products);
            }
        });
    }

    function loadCategories() {
        request("GetCategories", {}, function (res) {
            if (res.success) {
                var select = document.querySelector(".filters select");
                for (var i = 0; i < res.categories.length; i++) {
                    var opt = document.createElement("option");
                    opt.text = res.categories[i];
                    select.appendChild(opt);
                }
            }
        });
    }

    function filterProducts() {
        var category = document.querySelectorAll(".filters select")[0].value;
        var price = document.querySelectorAll(".filters select")[1].value;
        var sort = document.querySelectorAll(".filters select")[2].value;

        request("FilterProducts", {
            category: category,
            priceRange: price,
            sortBy: sort
        }, function (res) {
            if (res.success) {
                renderProducts(res.products);
            }
        });
    }

    function searchProducts() {
        var button = document.querySelector(".searchbar button");
        var input = document.querySelector(".searchbar input");

        button.addEventListener("click", function () {
            var query = input.value;
            request("SearchProducts", { query: query }, function (res) {
                if (res.success) {
                    renderProducts(res.products);
                }
            });
        });
    }

    function loadTopRated() {
        request("GetTopRated", {}, function (res) {
            if (res.success) {
                console.log("Top Products:", res.topProducts);
                console.log("Review Stats:", res.reviews);
                // charts or tables 
            }
        });
    }

    function init() {
        loadProducts();
        loadCategories();
        searchProducts();
        var selects = document.querySelectorAll(".filters select");
        for (var i = 0; i < selects.length; i++) {
            selects[i].addEventListener("change", filterProducts);
        }

        loadTopRated();
    }

    window.addEventListener("DOMContentLoaded", init);
})();
