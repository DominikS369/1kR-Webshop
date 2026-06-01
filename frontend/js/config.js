// Dynamische API-URL - funktioniert auf jedem Host und Port
// Verwendet den aktuellen Origin + relativen Pfad
const API_URL = (function() {
    // Option 1: Nutze window.location.origin (sicherste Methode)
    const protocol = window.location.protocol;  // "http:" oder "https:"
    const hostname = window.location.hostname;   // "localhost"
    const port = window.location.port;           // "" oder "8888"

    const portPart = port ? ":" + port : "";
    const origin = protocol + "//" + hostname + portPart;

    const url = origin + "/1kR-Webshop/backend/config/datahandler.php";
    console.log("✓ API_URL initialized:", url);
    return url;
})();

