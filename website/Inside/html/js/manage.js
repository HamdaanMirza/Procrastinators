(function () {
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

  function setupEventListeners() {
      var manageProductBtn = document.createElement("button");
      manageProductBtn.innerText = "Manage Products";
      manageProductBtn.onclick = manageProducts;
      document.body.appendChild(manageProductBtn);

      var manageCategoriesBtn = document.createElement("button");
      manageCategoriesBtn.innerText = "Manage Categories";
      manageCategoriesBtn.onclick = manageCategories;
      document.body.appendChild(manageCategoriesBtn);

      var manageRetailersBtn = document.createElement("button");
      manageRetailersBtn.innerText = "Manage Retailers";
      manageRetailersBtn.onclick = manageRetailers;
      document.body.appendChild(manageRetailersBtn);
  }

  function manageProducts() {
    var action = prompt("Enter action: add/edit/delete").toLowerCase();

    if (action === "add") {
      var name = prompt("Product name?");
      var brand = prompt("Brand?");
      var description = prompt("Description?");
      var imageURL = prompt("Image URL?");
      var categories = prompt("Categories?");
      fetchData(
        "AddProduct",
        {
          ProductName: name,
          Brand: brand,
          Description: description,
          ImageURL: imageURL,
          Categories: categories,
        },
        function () {
          filterAndSort();
        }
      );
    } else if (action === "edit") {
      var id = prompt("Enter product ID to edit:");
      var newName = prompt("New product name:");
      var newBrand = prompt("New product brand:");
      var newDescription = prompt("New product description:");
      var newImageURL = prompt("New product image URL:");
      var newCategories = prompt("New product categories:");
      fetchData(
        "EditProduct",
        {
          ProductID: parseInt(id),
          ProductName: newName,
          Brand: newBrand,
          Description: newDescription,
          ImageURL: newImageURL,
          Categories: newCategories,
        },
        function () {
          filterAndSort();
        }
      );
    } else if (action === "delete") {
      var deleteId = prompt("Enter product ID to delete:");
      if (
        confirm("Are you sure you want to delete product " + deleteId + "?")
      ) {
        fetchData(
          "DeleteProduct",
          {
            ProductID: parseInt(deleteId),
          },
          function () {
            filterAndSort();
          }
        );
      }
    } else {
      alert("Invalid action. Please enter add, edit, or delete.");
    }
  }

  function manageCategories() {
    var action = prompt("Enter action: add / edit / delete").toLowerCase();

    if (action === "add") {
      var name = prompt("Enter new category name:");
      if (name) {
        fetchData(
          "ManageCategory",
          {
            action: "add",
            name: name,
          },
          function () {
            alert("Category added.");
            loadFilteringData();
          }
        );
      }
    } else if (action === "edit") {
      var id = prompt("Enter the category ID to edit:");
      var newName = prompt("Enter the new name for category ID " + id + ":");
      if (id && newName) {
        fetchData(
          "ManageCategory",
          {
            action: "edit",
            id: parseInt(id),
            name: newName,
          },
          function () {
            alert("Category updated.");
            loadFilteringData();
          }
        );
      }
    } else if (action === "delete") {
      var deleteId = prompt("Enter the category ID to delete:");
      if (
        deleteId &&
        confirm("Are you sure you want to delete category ID " + deleteId + "?")
      ) {
        fetchData(
          "ManageCategory",
          {
            action: "delete",
            id: parseInt(deleteId),
          },
          function () {
            alert("Category deleted.");
            loadFilteringData();
          }
        );
      }
    } else {
      alert("Invalid action. Use add, edit, or delete.");
    }
  }

  function manageRetailers() {
    var action = prompt("Enter action: add / edit / delete").toLowerCase();

    if (action === "add") {
      var name = prompt("Enter new retailer name:");
      var country = prompt("Enter new retailer country:");
      var city = prompt("Enter new retailer city:");
      var street = prompt("Enter new retailer street:");
      var url = prompt("Enter new retailer URL:");
      if (name || country || city || street || url) {
        fetchData(
          "ManageRetailer",
          {
            action: "add",
            name: name,
            country: country,
            city: city,
            street: street,
            url: url
          },
          function () {
            alert("Retailer added.");
          }
        );
      }
    } else if (action === "edit") {
      var id = prompt("Enter new retailer id:");
      var name = prompt("Enter new retailer name:");
      var country = prompt("Enter new retailer country:");
      var city = prompt("Enter new retailer city:");
      var street = prompt("Enter new retailer street:");
      var url = prompt("Enter new retailer URL:");
      if (name || country || city || street || url) {
        fetchData(
          "ManageRetailer",
          {
            action: "add",
            id: id,
            name: name,
            country: country,
            city: city,
            street: street,
            url: url
          },
          function () {
            alert("Retailer updated.");
          }
        );
      }
    } else if (action === "delete") {
      var deleteId = prompt("Enter the retailer ID to delete:");
      if (
        deleteId &&
        confirm("Are you sure you want to delete retailer ID " + deleteId + "?")
      ) {
        fetchData(
          "ManageRetailer",
          {
            action: "delete",
            id: parseInt(deleteId),
          },
          function () {
            alert("Retailer deleted.");
          }
        );
      }
    } else {
      alert("Invalid action. Use add, edit, or delete.");
    }
  }

  window.onload = function () {
    if (localStorage.getItem("loggedIn") === "true") {
      var loginLink = document.getElementById("loginLink");
      var registerLink = document.getElementById("registerLink");
      if (loginLink) loginLink.style.display = "none";
      if (registerLink) registerLink.style.display = "none";
    }
    setupEventListeners();
  };
})();
