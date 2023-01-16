function ready(fn) {
  if (document.readyState !== "loading") {
    fn();
  } else {
    document.addEventListener("DOMContentLoaded", fn);
  }
}

ready(function () {
  let searchInput = new Autocomplete({
    selector: ".search-text",
    delay: 200,
  });

  // redirect to the item page.
  searchInput.onSelectedWord(function (identifier) {
    window.location.href = "/omeka/item/" + identifier;
  });
});
