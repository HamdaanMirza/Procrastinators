(function () {
  var products = [];
  var categories = [];
  var isAdmin = false; 

  function fetchData(type, payload, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "../../api.php", true);
    xhr.setRequestHeader("Content-Type", "application/json");
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4 && xhr.status === 200) {
        try{
          var response = JSON.parse(xhr.responseText);
          console.log(response.data);
          if(response && response.status === "success")
            callback(response);
          else
            console.log("Api error.");
        }
        catch(e){
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
      card.className = "product-card";
      card.innerHTML =
        '<img src="' + p.ImageURL + '" alt="' + p.ProductName + '" />' +
        '<h3>' + p.ProductName + '</h3>' +
        '<p>' + p.Brand + '</p>' +
        '<p>' + p.Description + '</p>' +
        '<p>R' + p.Price + '</p>' +
        '<p>Rating: ' + (p.AverageRating || 'N/A') + '</p>' +
        '<div class="bar" style="background:#ccc;width:' + (p.AverageRating || 0) * 20 + '%;height:10px;"></div>';
      if (isAdmin) {
        card.innerHTML +=
          '<button onclick="editProduct(' + p.ProductID + ')">Edit</button>' +
          '<button onclick="deleteProduct(' + p.ProductID + ')">Delete</button>';
      }
      card.addEventListener("click", function(product) {
            return function(){
                localStorage.setItem("selectedProductId", product.ProductID);
                window.location.href = "view.html";
            };
        }(p));
      container.appendChild(card);
    }
  }

  function loadTopRated() {
    fetchData("GetTopRated", null, function (response) {
      products = response.data;
      renderProducts(products);
    });
  }

  function loadCategories() {
    fetchData("GetCategories", null, function (response) {
      categories = response.data;
      var select = document.querySelector(".filters select");
      for (var i = 0; i < categories.length; i++) {
        var opt = document.createElement("option");
        opt.value = categories[i].CategoryID;
        opt.text = categories[i].CategoryName;
        select.appendChild(opt);
      }
    });
  }

  function filterAndSort() {
    var category = document.querySelectorAll(".filters select")[0].value;
    var priceRange = document.querySelectorAll(".filters select")[1].value;
    var sortBy = document.querySelectorAll(".filters select")[2].value;

    var filtered = products.filter(function (p) {
      var matchesCategory = !category || p.CategoryID == category;
      var price = parseFloat(p.Price);
      var matchesPrice = true;
      if (priceRange === "R0 - R500") matchesPrice = price <= 500;
      else if (priceRange === "R500 - R1000") matchesPrice = price > 500 && price <= 1000;
      else if (priceRange === "R1000 - R10000") matchesPrice = price > 1000 && price <= 10000;
      else if (priceRange === "R10000+") matchesPrice = price > 10000;
      return matchesCategory && matchesPrice;
    });

    filtered.sort(function (a, b) {
      if (sortBy === "Price: Low to High") return a.Price - b.Price;
      if (sortBy === "Price: High to Low") return b.Price - a.Price;
      return 0;
    });

    renderProducts(filtered);
  }

  function setupEventListeners() {
    var products = document.querySelectorAll(".product-card");

    var selects = document.querySelectorAll(".filters select");
    for (var i = 0; i < selects.length; i++) {
      selects[i].addEventListener("change", filterAndSort);
    }

    if (isAdmin) {
      var addProductBtn = document.createElement("button");
      addProductBtn.innerText = "Add Product";
      addProductBtn.onclick = addProduct;
      document.body.appendChild(addProductBtn);

      var addCategoryBtn = document.createElement("button");
      addCategoryBtn.innerText = "Manage Categories";
      addCategoryBtn.onclick = manageCategories;
      document.body.appendChild(addCategoryBtn);

      var addRetailerBtn = document.createElement("button");
      addRetailerBtn.innerText = "Manage Retailers";
      addRetailerBtn.onclick = manageRetailers;
      document.body.appendChild(addRetailerBtn);
    }
  }

  window.editProduct = function (id) {
    var newName = prompt("Enter new product name:");
    var newBrand = prompt("Enter new product Brand:");
    var newDescription = prompt("Enter new product Description:");
    var newImageURL = prompt("Enter new product ImageURL:");
    var newCategories = prompt("Enter new product Categories:");
    if (newName || newBrand || newDescription || newImageURL || newCategories) {
      fetchData("EditProduct", {ProductID: id, ProductName: newName, Brand: newBrand, Description: newDescription, ImageURL: newImageURL, Categories: newCategories}, function () {
        loadTopRated();
      });
    }
  };

  window.deleteProduct = function (id) {
    if (confirm("Are you sure you want to delete this product?")) {
      fetchData("DeleteProduct", { ProductID: id }, function () {
        loadTopRated();
      });
    }
  };

  function addProduct() {
    var name = prompt("Product name?");
    var brand = prompt("Brand?");
    var description = prompt("Description?");
    var imageURL = prompt("Image URL?");
    var categories = prompt("Categories?");
    fetchData("AddProduct", {
      ProductName: name,
      Brand: brand,
      Description: description,
      ImageURL: imageURL,
      Categories: categories
    }, function () {
      loadTopRated();
    });
  }

  function manageCategories() {
    var action = prompt("Enter action: add/edit/delete");
    var name = prompt("Enter category name:");
    var id = prompt("Enter category ID (if editing or deleting):");
    fetchData("ManageCategory", { action: action, name: name, id: id }, function () {
      loadCategories();
    });
  }

  function manageRetailers() {
    var action = prompt("Enter action: add/edit/delete");
    var name = prompt("Enter retailer name:");
    var id = prompt("Enter retailer ID (if editing or deleting):");
    fetchData("ManageRetailer", { action: action, name: name, id: id }, function () {
      alert("Retailer updated.");
    });
  }

  window.onload = function () {
    loadCategories();
    loadTopRated();
    setupEventListeners();
  };
})();
