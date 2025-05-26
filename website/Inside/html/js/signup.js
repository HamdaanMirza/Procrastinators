function saveApiKey(apiKey) {
    try {
        localStorage.setItem('userApiKey', apiKey);
        console.log('API Key saved to localStorage:', apiKey);
    }
    catch (error) {
        console.error('Failed to save API key to localStorage:', error);
    }
}

function getSavedApiKey() {
    try{
        return localStorage.getItem('userApiKey');
    }
    catch (error){
        console.error('Failed to retrieve API key from localStorage:', error);
        return null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.querySelector('#signupForm');
    const apiUrl = '../../api.php';
    
    if (getSavedApiKey()) {
        alert('User already logged in!');
        window.location.href = '../html/index.html'; 
    }

    if (signupForm) {
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = signupForm.querySelector('input[name="username"]').value;
            const email = signupForm.querySelector('input[name="email"]').value;
            const password = signupForm.querySelector('input[name="password"]').value;

            try {
                console.log('Sending signup request to:', apiUrl);
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        Type: 'Register',
                        UserName: username,
                        Email: email,
                        Password: password
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
                    const apiKey = result.data.Apikey;
                    saveApiKey(apiKey);
                    alert('Registration successful!');
                    signupForm.reset();
                    window.location.href = '../html/index.html';
                    
                }
                else {
                    alert('Error: ' + result.data);
                }
            }
            catch (error) {
                alert('An error occurred: ' + error.message);
                console.error('Fetch error:', error);
            }
        });
    } else {
        console.error('Signup form not found');
    }
});