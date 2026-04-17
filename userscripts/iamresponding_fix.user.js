// ==UserScript==
// @name         IamResponding Fit Agency Name
// @namespace    http://tampermonkey.net/
// @version      1.0
// @description  Dynamically adjust the font size of the agency name on the IamResponding dashboard so it fits without truncating.
// @author       FireDisplay
// @match        *://dashboard.iamresponding.com/*
// @grant        none
// ==/UserScript==

(function() {
    'use strict';

    function adjustAgencyNameSize() {
        const container = document.querySelector('.iar-agency-name');
        const textElement = document.querySelector('[data-cy="Agency-Name"]');

        if (!container || !textElement) return;

        // Reset to recalculate
        textElement.style.fontSize = '';
        textElement.style.whiteSpace = 'nowrap';
        textElement.style.display = 'inline-block';

        const containerWidth = container.clientWidth;
        const textWidth = textElement.scrollWidth;

        if (textWidth > containerWidth && containerWidth > 0) {
            const scale = containerWidth / textWidth;
            const currentSize = parseFloat(window.getComputedStyle(textElement).fontSize);
            // Give it a tiny bit of breathing room (0.95)
            const newSize = Math.floor(currentSize * scale * 0.95);
            textElement.style.fontSize = `${newSize}px`;
        }
    }

    const observer = new MutationObserver(() => {
        if (document.querySelector('.iar-agency-name') && document.querySelector('[data-cy="Agency-Name"]')) {
            adjustAgencyNameSize();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
    window.addEventListener('resize', adjustAgencyNameSize);

    // Initial check
    setTimeout(adjustAgencyNameSize, 1000);
})();
