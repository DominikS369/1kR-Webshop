// API-URL via relativer Pfad - funktioniert überall!
// Von /frontend/sites/index.html oder /frontend/sites/login.html zu /backend/config/datahandler.php
// = ../../backend/config/datahandler.php
const API_BASE = (function() {
    const relativePath = "../../backend/config/datahandler.php";
    const url = new URL(relativePath, window.location.href).href;
    console.log("API_BASE:", url);
    return url;
})();

