// Disable right-click context menu
document.addEventListener('contextmenu', function(event) {
    event.preventDefault();
    return false;
});

// Disable keyboard shortcuts for developer tools
document.addEventListener('keydown', function(event) {
    // Disable F12 (Developer Tools)
    if (event.key === 'F12') {
        event.preventDefault();
        return false;
    }
    
    // Disable Ctrl+Shift+I (Inspect Element)
    if (event.ctrlKey && event.shiftKey && event.key === 'I') {
        event.preventDefault();
        return false;
    }
    
    // Disable Ctrl+Shift+C (Inspect Element - alternative)
    if (event.ctrlKey && event.shiftKey && event.key === 'C') {
        event.preventDefault();
        return false;
    }
    
    // Disable Ctrl+Shift+J (Console)
    if (event.ctrlKey && event.shiftKey && event.key === 'J') {
        event.preventDefault();
        return false;
    }
    
    // Disable Ctrl+Shift+K (Console - Firefox)
    if (event.ctrlKey && event.shiftKey && event.key === 'K') {
        event.preventDefault();
        return false;
    }
    
    // Disable Ctrl+U (View Page Source)
    if (event.ctrlKey && event.key === 'u') {
        event.preventDefault();
        return false;
    }
});

// Disable right-click on images
document.addEventListener('dragstart', function(event) {
    if (event.target.tagName === 'IMG') {
        event.preventDefault();
        return false;
    }
});