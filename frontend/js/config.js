// API-URL - funktioniert überall auf XAMPP und MAMP
const API_BASE = (function() {
    // Nutze window.location.origin + basierend auf aktueller Dateistruktur
    // Frontend ist immer in /frontend/sites/
    // Backend ist immer in /backend/config/

    const origin = window.location.origin;
    const pathname = window.location.pathname;

    // Extrahiere Projektroot: z.B. aus /1kR-webshop/frontend/sites/login.html
    const parts = pathname.split("/");
    let projectRoot = "";

    // Finde den Projektordner (sollte das erste meaningful Segment sein)
    for (let i = 1; i < parts.length; i++) {
        if (parts[i] === "frontend") {
            projectRoot = "/" + parts.slice(1, i).join("/");
            break;
        }
    }

    const url = origin + projectRoot + "/backend/config/datahandler.php";
    console.log("API_BASE configured:", url);
    return url;
})();

window.API_BASE = API_BASE;
