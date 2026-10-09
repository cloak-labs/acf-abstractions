/* Load only the chosen InnerBlocks template; let ACF add and initialize the row. */
(function ($, acf) {
  "use strict";
  if (!acf) return;

  const installed = new WeakSet();

  function install(field) {
    if (installed.has(field) || field.$el.attr("data-cloakwp-lazy-layouts") !== "1") return;
    installed.add(field);
    const add = field.add;
    const pending = new Map();
    let notice;

    function template(layout) {
      const clone = field.$clone(layout);
      if (clone.find(".acf-field").length) return Promise.resolve();
      if (pending.has(layout)) return pending.get(layout);

      if (pending.size === 0) {
        field.showNotice({ text: acf.__("Loading block fields…"), type: "info", dismiss: false });
        notice = field.notice;
      }
      field.$button().attr("aria-busy", "true");
      const request = Promise.resolve($.ajax({
        url: acf.get("ajaxurl"), type: "POST", dataType: "json",
        data: acf.prepareForAjax({
          action: "cloakwp/acf/inner_blocks/layout",
          field_key: field.get("key"), nonce: field.get("nonce"), layout,
          input_name: field.getInputName(), post_id: acf.get("post_id"),
          options_page: new URL(window.location.href).searchParams.get("page") || "",
        }),
      })).then(function (response) {
        if (!response.success || !response.data?.html) throw new Error("Missing block template");
        if (!field.$el[0].isConnected) return;
        const rendered = $(response.data.html).filter(".layout");
        if (rendered.length !== 1 || rendered.attr("data-layout") !== layout
          || !rendered.find(".acf-field").length) throw new Error("Invalid block template");
        acf.disable(rendered, field.cid);
        clone.replaceWith(rendered);
      }).finally(function () {
        pending.delete(layout);
        if (pending.size === 0) {
          field.$button().removeAttr("aria-busy");
          if (field.notice === notice) field.removeNotice();
          notice = null;
        }
      });
      pending.set(layout, request);
      return request;
    }

    field.add = function (args) {
      const layout = args?.layout;
      if (!layout || field.$clone(layout).find(".acf-field").length) return add.apply(field, arguments);
      if (!field.validateAdd()) return false;
      return template(layout).then(function () {
        if (!field.$el[0].isConnected || (args.before && !args.before[0]?.isConnected)) return false;
        // Multiple clicks can finish together; recheck limits against current rows.
        const clone = field.$clone(layout);
        const max = Number(clone.data("max"));
        if (max && field.countLayouts(layout) >= max) return false;
        return add.call(field, args);
      }).catch(function () {
        if (field.$el[0].isConnected) {
          field.showNotice({ text: acf.__("Could not load block fields. Please try again."), type: "error" });
        }
        return false;
      });
    };
  }

  acf.addAction("new_field/type=flexible_content", install);
  acf.addAction("ready_field/type=flexible_content", install);
  acf.addAction("append_field/type=flexible_content", install);
})(jQuery, window.acf);
