(function () {
  var products = [];
  var categories = [];

  function fetchData(type, payload, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "../../api.php", true);
    xhr.setRequestHeader("Content-Type", "application/json");
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4 && xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          console.log(response.data);
          if (response && response.status === "success") callback(response);
          else console.log("Api error.");
        } catch (e) {
          console.error("Error from api call: ", e, xhr.responseText);
        }
      }
    };
    var data = payload || {};
    data.Type = type;
    console.log(JSON.stringify(data));
    xhr.send(JSON.stringify(data));
  }

  function renderProducts(data) {
    var container = document.querySelector(".products");
    container.innerHTML = "";
    for (var i = 0; i < data.length; i++) {
      var p = data[i];
      var card = document.createElement("div");
      card.className = "product";
      card.innerHTML =
        '<img src="' +
        p.ImageURL +
        '" alt="' +
        p.ProductName +
        '" />' +
        "<h3>" +
        p.ProductName +
        "</h3>" +
        "<p>" +
        p.Brand +
        "</p>" +
        "<p>Rating: " +
        (p.AverageRating || "N/A") +
        "</p>" +
        '<div class="bar" style="background:#ccc;width:' +
        (p.AverageRating || 0) * 20 +
        '%;height:10px;"></div>';
      card.addEventListener(
        "click",
        (function (product) {
          return function () {
            localStorage.setItem("selectedProductId", product.ProductID);
            window.location.href = "view.html";
          };
        })(p)
      );
      container.appendChild(card);
    }
  }

  function loadFilteringData() {
    fetchData("GetCategories", null, function (response) {
      categories = response.data;
      var select = document.querySelectorAll(".filters select")[0];
      for (var i = 0; i < categories.length; i++) {
        var opt = document.createElement("option");
        opt.value = categories[i].CategoryID;
        opt.text = categories[i].CategoryName;
        select.appendChild(opt);
      }
    });
    fetchData("GetBrands", null, function (response) {
      brands = response.data;
      var select = document.querySelectorAll(".filters select")[1];
      for (var i = 0; i < brands.length; i++) {
        var opt = document.createElement("option");
        opt.value = brands[i].Brand;
        opt.text = brands[i].Brand;
        select.appendChild(opt);
      }
    });
  }

  function filterAndSort() {
    var category = document.querySelectorAll(".filters select")[0].value;
    var brand = document.querySelectorAll(".filters select")[1].value;
    var sort = document.querySelectorAll(".filters select")[2].value;
    var searchQuery = document
      .querySelector(".searchbar input")
      .value.toLowerCase();

    var endpoint = "GetTopRated";
    if (sort === "Most Reviewed") endpoint = "GetMostReviewed";
    else if (sort === "Recently Listed") endpoint = "GetRecentlyListed";
    else if (sort === "Most Listed") endpoint = "GetMostListed";

    fetchData(endpoint, null, function (response) {
      products = response.data;
      applyFilters();
    });
  }

  function applyFilters() {
    var category = document.querySelectorAll(".filters select")[0].value;
    var brand = document.querySelectorAll(".filters select")[1].value;
    var searchQuery = document
      .querySelector(".searchbar input")
      .value.toLowerCase();

    var filtered = products.filter(function (p) {
      var matchCategory = category === "0" || p.CategoryID == category;
      var matchBrand = brand === "0" || p.Brand === brand;
      var matchSearch =
        p.ProductName && p.ProductName.toLowerCase().includes(searchQuery);
      return matchCategory && matchBrand && matchSearch;
    });
    renderProducts(filtered);
  }

  function setupEventListeners() {
    document
      .querySelector(".searchbar button")
      .addEventListener("click", filterAndSort);
    document
      .querySelector(".searchbar input")
      .addEventListener("keypress", function (e) {
        if (e.key === "Enter") filterAndSort();
      });

    var selects = document.querySelectorAll(".filters select");
    for (var i = 0; i < selects.length; i++) {
      selects[i].addEventListener("change", filterAndSort);
    }
  }

  window.onload = function () {
    loadFilteringData();
    filterAndSort();
    setupEventListeners();
  };
})();

document.addEventListener('DOMContentLoaded', () => {
  const manageLink = document.getElementById('manage-link');
  const loginLink = document.getElementById('login-link');
  const signupLink = document.getElementById('signup-link');
  const isLoggedIn = localStorage.getItem('loggedIn') === 'true';

  if(!isLoggedIn || localStorage.getItem('isAdmin') === "false") {
        manageLink.style.display = "none";
  }

  if (isLoggedIn) {
      loginLink.style.display = 'none';
      signupLink.innerHTML = 'Logout';
      signupLink.href = '#';
      signupLink.onclick = logout;
    }
});

function logout() {
  localStorage.removeItem('userApiKey');
  localStorage.removeItem('userData');
  localStorage.removeItem('isAdmin');
  localStorage.removeItem('loggedIn');
  window.location.href = './index.html';
}