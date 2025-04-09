import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

const firebaseConfig = {
    apiKey: "AIzaSyBz1_t6bzHZ_P9f5-5srnGsH7dF1pdUOcM",
    authDomain: "signup-48a4e.firebaseapp.com",
    projectId: "signup-48a4e",
    storageBucket: "signup-48a4e.appspot.com",
    messagingSenderId: "279728981782",
    appId: "1:279728981782:web:ee9859f7b5585b229ff237",
    measurementId: "G-GQ6D9VCBRP"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const provider = new GoogleAuthProvider();

// 🔹 Force account selection
provider.setCustomParameters({
    prompt: "select_account"
});

document.addEventListener("DOMContentLoaded", function () {
    const googleSignup = document.getElementById("googleSignInBtn");

    if (!googleSignup) {
        console.error("Google Sign-In Button Not Found!");
        return;
    }

    googleSignup.addEventListener("click", async function () {
        try {
            const result = await signInWithPopup(auth, provider);
            const user = result.user;
            console.log("Google Sign-In Successful:", user);

            const response = await fetch("login.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    google_signin: "1",
                    email: user.email,
                    name: user.displayName,
                    google_id: user.uid
                })
            });

            const resultData = await response.json();
            if (resultData.status === "success") {
                console.log("Login Successful:", resultData.message);
                window.location.href = resultData.redirect; // Redirect to user1.php
            } else {
                console.error("Login Error:", resultData.message);
                alert(resultData.message);
            }

        } catch (error) {
            console.error("Google Sign-In Error:", error);
            alert("Google Sign-In Failed. Please try again.");
        }
    });
});
