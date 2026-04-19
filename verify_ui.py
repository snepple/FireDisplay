from playwright.sync_api import sync_playwright
import time
import subprocess
import os

def test_ui():
    server = subprocess.Popen(["php", "-S", "127.0.0.1:8000"])
    time.sleep(2)  # Wait for server to start

    try:
        with sync_playwright() as p:
            browser = p.chromium.launch()

            page = browser.new_page(viewport={"width": 1920, "height": 1080})

            page.goto("http://127.0.0.1:8000/index.php?token=ChangeMe456Token!")
            time.sleep(5)

            # Force dashboard page to be visible for screenshot
            page.evaluate("document.getElementById('page-dashboard').style.display = 'flex';")
            time.sleep(1)

            page.screenshot(path="index_dark_1080p.png")

            print("Screenshot captured.")
            browser.close()
    finally:
        server.terminate()

if __name__ == "__main__":
    test_ui()
