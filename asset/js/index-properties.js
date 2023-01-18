(function ($) {
  function addToIndexProperties(term, label) {
    var id = "index-property-" + term;
    if (document.getElementById(id)) {
      return;
    }
    var indexPropertyRow = $('<div class="index-property row"></div>');
    indexPropertyRow.attr("id", id);
    indexPropertyRow.append(
      $("<span>", { class: "property-label", text: label })
    );
    indexPropertyRow.append(
      $(
        '<ul class="actions"><li><a class="o-icon-delete remove-index-property" href="#"></a></li></ul>'
      )
    );
    indexPropertyRow.append(
      $("<input>", { type: "hidden", name: "index-properties[]", value: term })
    );
    $("#index-properties-list").append(indexPropertyRow);
  }

  function indexProperty(propertySelectorChild) {
    var term = $(propertySelectorChild).data("propertyTerm");
    var label = $(propertySelectorChild).data("childSearch");
    addToIndexProperties(term, label);
  }

  $(document).ready(function () {
    // Add job for adding index.
    $("#search-opts").on("click", ".index-btn", function (e) {
      e.preventDefault();
      $(this).attr("disabled", "disabled");
      var http = new XMLHttpRequest();
      var url = $(this).data("url");
      http.open("POST", url, true);
      http.onreadystatechange = function () {
        if (http.readyState == 4) {
          if (this.status == 200) {
            $("#search-opts .index-btn").removeAttr("disabled");
          }
        }
      };
      http.send(null);
    });

    $("#property-selector li.selector-child").on("click", function (e) {
      e.stopPropagation();
      indexProperty(this);
    });

    $("#index-properties-list").on(
      "click",
      ".remove-index-property",
      function (e) {
        e.preventDefault();
        $(this).closest(".index-property").remove();
      }
    );

    $.each(
      $("#index-properties-list").data("indexProperties"),
      function (index, value) {
        var propertySelectorChild = $(
          '#property-selector li.selector-child[data-property-term="' +
            value +
            '"]'
        );
        if (propertySelectorChild.length) {
          indexProperty(propertySelectorChild);
        }
      }
    );
  });
})(jQuery);
