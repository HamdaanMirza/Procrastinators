var product = null;

document.addEventListener("DOMContentLoaded", function(){
    var productID = localStorage.getItem("selectedProductId");
    if(!productID){
        alert("Product id not found. Redirecting to Products page.");
        window.location.href = "index.html";
        return;
    }
    loadProduct(productID);
    var submitButton = document.getElementById("submit-review");
    submitButton.addEventListener("click", handleSubmit);
});

function getAverage(productID, callback) {
    if (!productID || isNaN(parseInt(productID))) {
        console.error("Invalid ProductID");
        callback(0);
        return;
    }

    var requestBody = {
        Type: "GetAverageRating",
        ProductID: parseInt(productID)
    };
    var request = new XMLHttpRequest();
    request.open("POST", "../../api.php", true);
    request.setRequestHeader("Content-Type", "application/json");
    request.onreadystatechange = function() {
        if (request.readyState === 4) {
            if(request.status === 200){
                try{
                    var response = JSON.parse(request.responseText);
                    if (response.status === "success") {
                        console.log("Average rating:", response.data);
                        callback(response.data);
                    }
                    else{
                        console.error("API error:", response.data);
                        callback(0);
                    }
                } catch (e) {
                    console.error("Failed to parse response:", e);
                    callback(0);
                }
            }
            else {
                console.error("Failed to fetch average rating:", request.statusText);
                callback(0);
            }
        }
    };
    request.send(JSON.stringify(requestBody));
}

function handleSubmit(){
    var userData = localStorage.getItem("userData");
    var user = null;
    if(userData)
        user = JSON.parse(userData);
    else{
        alert("Please log in to submit a review.");
        return;
    }
    if(!user.UserID){
        alert("Please log in to submit a review.");
        return;
    }
    var comment = document.getElementById("comment").value;
    var ratingInputs  = document.querySelectorAll('#ratingBtn input[name="rating"]');
    var selectedRating = null;
    for (let i = 0; i < ratingInputs.length; i++) {
        if (ratingInputs[i].checked) {
            selectedRating = ratingInputs[i].value;
            break;
        }
    }
    if(!selectedRating){
        alert("Please provide a rating.");
        return;
    }

    var checkReview = {
        Type: "CheckReview",
        UserID: parseInt(user.UserID),
        ProductID: parseInt(localStorage.getItem("selectedProductId"))
    };
    console.log(JSON.stringify(checkReview));
    var checkRequest = new XMLHttpRequest();
    checkRequest.open("POST", "../../api.php", true);
    checkRequest.setRequestHeader("Content-Type", "application/json");
    checkRequest.onreadystatechange = function() {
        if(checkRequest.readyState === 4){
            if(checkRequest.status === 200){
                var response = JSON.parse(checkRequest.responseText);
                var requestBody = {
                    Type: response.status === "success" ? "UpdateReview" : "InsertReview",
                    UserID: parseInt(user.UserID),
                    ProductID: parseInt(localStorage.getItem("selectedProductId")),
                    Rating: parseInt(selectedRating),
                    Comment: comment
                };
                console.log("Request: " + JSON.stringify(requestBody));
                var submitRequest = new XMLHttpRequest();
                submitRequest.open("POST", "../../api.php", true);
                submitRequest.setRequestHeader("Content-Type", "application/json");
                submitRequest.onreadystatechange = function() {
                    if (submitRequest.readyState === 4) {
                        if (submitRequest.status === 200) {
                            alert(response.data.exists ? "Review updated successfully!" : "Review submitted successfully!");
                            document.getElementById("comment").value = "";
                            ratingInputs.forEach(input => input.checked = false);
                            loadProduct(localStorage.getItem("selectedProductId"));
                        }
                        else
                            alert("Failed to process your review. Please try again later.");
                    }
                };
                submitRequest.send(JSON.stringify(requestBody));
            }
            else
                alert("Failed to check existing review");
        }
    };
    checkRequest.send(JSON.stringify(checkReview));
}

function loadProduct(productId){
    var requestBody = {
        Type: "GetProduct",
        ProductID: productId
    };
    console.log("Product ID: " + productId);
    var request = new XMLHttpRequest();
    request.open("POST", '../../api.php', true);
    request.setRequestHeader("Content-Type", "application/json");
    request.onreadystatechange = function(){
        if(request.readyState === 4){
            if(request.status === 200){
                var response = JSON.parse(request.responseText);
                product = response.data;
                displayProduct();
            }
            else
                alert("Failed to fetch products:" + request.statusText);
        }
    };
    request.send(JSON.stringify(requestBody));
}

function displayProduct(){
    getAverage(localStorage.getItem("selectedProductId"), function(averageRating) {
        document.getElementById("rating").textContent = "Rating: " + (averageRating ? averageRating.toFixed(2) : "0");
    });
    document.getElementById("name").textContent = "Name: " + product.ProductName;
    document.getElementById("brand").textContent = "Brand: " + product.Brand;
    document.getElementById("description-text").textContent = product.Description;
    document.getElementById("pic").src = product.ImageURL;
    document.getElementById("pic").alt = product.ProductName;
    if(product.Listings[0].Date !== null && product.Listings[0].Date !== undefined)
        document.getElementById("date").textContent = "Date: " + product.Listings[0].Date;
    else
        document.getElementById("date").textContent = "";
    if(product.Listings[0].Price !== null && product.Listings[0].Price !== undefined)
        document.getElementById("price").textContent = "Price: R" + product.Listings[0].Price;
    else
        document.getElementById("price").textContent = "No product listing available.";
}
