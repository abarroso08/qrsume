
document.addEventListener("DOMContentLoaded", function() {
    let clicks = 0;
    let scrollDepth = 0;
    
    // Count Clicks
    document.addEventListener("click", function() {
        clicks++;
    });

    // Track Scroll Depth
    window.addEventListener("scroll", function() {
        let scrolled = Math.floor((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
        if (scrolled > scrollDepth) scrollDepth = scrolled;
    });

    // Send Data Before Leaving Page
    window.addEventListener("beforeunload", function() {
        navigator.sendBeacon("track_page_interactions.php", JSON.stringify({
            clicks: clicks,
            scrollDepth: scrollDepth
        }));
    });
});
