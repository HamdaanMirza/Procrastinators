(function () {
    var API_URL = "../../../api.php";

    function fetchData(type, payload, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", API_URL, true);
        xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                callback(JSON.parse(xhr.responseText));
            }
        };
        xhr.send(JSON.stringify(Object.assign({ type: type }, payload || {})));
    }

    function renderProducts(products) {
        var container = document.querySelector('.products');
        container.innerHTML = '';
        for (var i = 0; i < products.length; i++) {
            var p = products[i];
            var div = document.createElement('div');
            div.className = 'product-card';
            div.innerHTML =
                '<img src="' + p.ImageURL + '" alt="' + p.ProductName + '">' +
                '<h3>' + p.ProductName + '</h3>' +
                '<p>' + p.Description + '</p>' +
                '<p><strong>Brand:</strong> ' + p.Brand + '</p>' +
                '<p><strong>Price:</strong> R' + p.Price + '</p>';
            container.appendChild(div);
        }
    }

    function renderChart(data) {
        var ctx = document.getElementById('topRatedChart').getContext('2d');
        var chartData = {
            labels: [],
            datasets: [{
                label: 'Review Count',
                data: [],
                backgroundColor: 'rgba(54, 162, 235, 0.6)'
            }]
        };

        for (var i = 0; i < data.length; i++) {
            chartData.labels.push(data[i].ProductName);
            chartData.datasets[0].data.push(data[i].ReviewCount);
        }

        new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function initFilters(categories) {
        var select = document.querySelector('.filter-box select');
        for (var i = 0; i < categories.length; i++) {
            var opt = document.createElement('option');
            opt.value = categories[i].CategoryID;
            opt.innerText = categories[i].CategoryName;
            select.appendChild(opt);
        }
    }

    function init() {
        fetchData("GetTopRated", null, function (response) {
            renderChart(response);
            renderProducts(response.slice(0, 15));
        });

        fetchData("GetAllCategories", null, function (response) {
            initFilters(response);
        });

        fetchData("GetAllProducts", null, function (response) {
            window.allProducts = response; // store globally for filtering/sorting
        });
    }

    init();
})();
