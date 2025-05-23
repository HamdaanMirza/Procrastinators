function saveApiKey(apiKey) {
    try {
        localStorage.setItem('userApiKey', apiKey);
        console.log('API Key saved to localStorage:', apiKey);
    } catch (error) {
        console.error('Failed to save API key to localStorage:', error);
    }
}

function saveUserData(userData) {
    try {
        localStorage.setItem('userData', JSON.stringify(userData));
        console.log('User data saved to localStorage:', userData);
    } catch (error) {
        console.error('Failed to save user data to localStorage:', error);
    }
}

function getSavedApiKey() {
    try {
        return localStorage.getItem('userApiKey');
    } catch (error) {
        console.error('Failed to retrieve API key from localStorage:', error);
        return null;
    }
}

function getSavedUserData() {
    try {
        const userData = localStorage.getItem('userData');
        return userData ? JSON.parse(userData) : null;
    } catch (error) {
        console.error('Failed to retrieve user data from localStorage:', error);
        return null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.querySelector('form');
    const apiUrl = '../../../api.php';

    const existingApiKey = getSavedApiKey();
    const existingUserData = getSavedUserData();
    
    if (existingApiKey && existingUserData) {
        alert('User already logged in!');
        window.location.href = '/Inside/html/index.html'; 
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = loginForm.querySelector('input[name="email"]').value;
            const password = loginForm.querySelector('input[name="password"]').value;

            try {
                console.log('Sending login request to:', apiUrl);
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: 'loginUser',
                        email: email,
                        password: password
                    })
                });

                const responseText = await response.text();
                console.log('Raw response:', responseText);

                let jsonString = responseText;
                
                const jsonStart = responseText.indexOf('{');
                if (jsonStart > 0) {
                    jsonString = responseText.substring(jsonStart);
                    console.log('Cleaned JSON string:', jsonString);
                }

                const result = JSON.parse(jsonString);

                if (result.status === 'success') {
                    const userData = result.data.user;
                    
                    if (userData.Apikey) 
                        saveApiKey(userData.Apikey);
                    
                    saveUserData(userData);
                    
                    alert('Login successful!');
                    
                    loginForm.reset();
                    
                    window.location.href = '/Inside/html/index.html';;
                    
                } else {
                    alert('Login failed: ' + result.data);
                }
            } catch (error) {
                alert('An error occurred: ' + error.message);
                console.error('Login error:', error);
            }
        });
    } else {
        console.error('Login form not found');
    }
});