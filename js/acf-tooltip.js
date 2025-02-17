wp.domReady(() => {
  /* Adds a tooltip custom class to ACF fields with instructions, including in the block editor where the DOM is constantly re-rendered */
  async function addAcfTooltipClassNames() {
    MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

    // observe DOM changes within the Block Editor container, so we can run mutationCallback() to add tooltip class names
    var observer = new MutationObserver(mutationCallback);

    const gutenbergSidebar = await waitForElement(
      ".interface-interface-skeleton__sidebar"
    );

    if (gutenbergSidebar && gutenbergSidebar instanceof Node) {
      observer.observe(gutenbergSidebar, {
        attributes: true,
        subtree: true,
      });
    }

    // fires when a DOM mutation occurs within Block Editor's sidebar (i.e. when you select a block etc.)
    function mutationCallback(mutations, observer) {
      jQuery(".acf-field .acf-label:has(p.description)").addClass(
        "cloakwp-acf-tooltip"
      );
      jQuery(".acf-repeater .acf-th:has(p.description)").addClass(
        "cloakwp-acf-tooltip"
      );
    }
  }

  addAcfTooltipClassNames();
});

/**
 * This function tries to find the element in the DOM matching the provided selector. If it
 * can't find the element, it waits for a specified interval (default 500ms) and tries again,
 * up to the maximum number of attempts. If the element isn't found after all attempts, the
 * function rejects the promise.
 *
 * You can adjust the interval parameter if you want to change how long it waits between retries.
 * Also, note that DOM querying and timeouts are inherently synchronous operations, so this function
 * returns a Promise, allowing you to use it in an asynchronous context.
 */
function waitForElement(
  selector,
  maxAttempts = 10,
  interval = 500,
  attempts = 0
) {
  return new Promise((resolve, reject) => {
    // Try to find the element using the selector
    const element = document.querySelector(selector);

    if (element) {
      // Element found, resolve the promise with the found element
      resolve(element);
    } else if (attempts < maxAttempts) {
      // Element not found, and still have attempts left
      // Wait for a bit and then try again
      setTimeout(() => {
        waitForElement(selector, maxAttempts, interval, attempts + 1)
          .then(resolve)
          .catch(reject);
      }, interval);
    } else {
      // Out of attempts, reject the promise
      reject(
        new Error(
          `Failed to find element with selector "${selector}" after ${maxAttempts} attempts`
        )
      );
    }
  });
}
