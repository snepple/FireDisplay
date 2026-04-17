function adjustAgencyNameSize() {
  const container = document.querySelector('.iar-agency-name');
  const textElement = document.querySelector('[data-cy="Agency-Name"]');

  if (!container || !textElement) return;

  // Reset any previous inline styles to recalculate correctly
  textElement.style.fontSize = '';
  textElement.style.whiteSpace = 'nowrap';
  textElement.style.display = 'inline-block';

  // Calculate the available width
  const containerWidth = container.clientWidth;
  const textWidth = textElement.scrollWidth;

  if (textWidth > containerWidth && containerWidth > 0) {
    const scale = containerWidth / textWidth;
    const currentSize = parseFloat(window.getComputedStyle(textElement).fontSize);
    // Scale down the font size with a slight margin (0.95)
    const newSize = Math.floor(currentSize * scale * 0.95);
    textElement.style.fontSize = `${newSize}px`;
  }
}

// Observe DOM for the element to appear or change
const observer = new MutationObserver(() => {
  if (document.querySelector('.iar-agency-name') && document.querySelector('[data-cy="Agency-Name"]')) {
    adjustAgencyNameSize();
  }
});

observer.observe(document.body, { childList: true, subtree: true });

// Also handle window resizing
window.addEventListener('resize', adjustAgencyNameSize);

// Try to run it right away just in case
adjustAgencyNameSize();
