// config.js definiert die API_BASE für die AJAX-Calls
const API_BASE = (function() {

    const origin = window.location.origin;
    const pathname = window.location.pathname;

    const parts = pathname.split("/");
    let projectRoot = "";

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