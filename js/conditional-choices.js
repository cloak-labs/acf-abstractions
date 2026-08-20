/**
 * CloakWP Conditional Choices
 *
 * Dynamically shows/hides ACF choice-field options based on sibling field state.
 * Config is supplied via data-conditional-choices on the target field wrapper.
 */
(function () {
  "use strict";

  var ATTR = "data-conditional-choices";
  var states = typeof WeakMap !== "undefined" ? new WeakMap() : null;
  var controllerIndex = {};
  var queue = [];
  var queued = typeof WeakSet !== "undefined" ? new WeakSet() : null;
  var flushing = false;

  function parseConfig($el) {
    var raw = $el && $el.attr ? $el.attr(ATTR) : null;
    if (!raw) return null;
    try {
      return typeof raw === "string" ? JSON.parse(raw) : raw;
    } catch (e) {
      console.warn("[cloakwp/conditional-choices] Invalid JSON payload.", e);
      return null;
    }
  }

  function normalizeValue(value) {
    if (value === null || typeof value === "undefined" || value === false) {
      return [];
    }
    if (Array.isArray(value)) {
      return value.map(String);
    }
    return [String(value)];
  }

  function isEmpty(value) {
    if (value === null || typeof value === "undefined" || value === false) {
      return true;
    }
    if (Array.isArray(value)) {
      return value.length === 0;
    }
    return value === "";
  }

  function equals(actual, expected) {
    var actualValues = normalizeValue(actual);
    var expectedString = String(expected);
    return actualValues.some(function (item) {
      return item === expectedString;
    });
  }

  function contains(actual, expected) {
    var needle = String(expected);
    return normalizeValue(actual).some(function (item) {
      return item.indexOf(needle) !== -1;
    });
  }

  function matchesPattern(actual, expected) {
    var pattern = String(expected);
    var source = pattern.charAt(0) === "/" ? pattern.slice(1, pattern.lastIndexOf("/")) : pattern;
    var flags =
      pattern.charAt(0) === "/" ? pattern.slice(pattern.lastIndexOf("/") + 1) : "";
    var re;
    try {
      re = new RegExp(source, flags);
    } catch (e) {
      return false;
    }
    return normalizeValue(actual).some(function (item) {
      return re.test(item);
    });
  }

  function compareNumeric(actual, expected) {
    var a = Number(Array.isArray(actual) ? actual[0] : actual);
    var b = Number(expected);
    if (Number.isNaN(a) || Number.isNaN(b)) return 0;
    return a === b ? 0 : a > b ? 1 : -1;
  }

  function ruleMatches(rule, actual) {
    var operator = rule.operator;
    var expected = Object.prototype.hasOwnProperty.call(rule, "value")
      ? rule.value
      : null;

    switch (operator) {
      case "==empty":
        return isEmpty(actual);
      case "!=empty":
        return !isEmpty(actual);
      case "==":
        return equals(actual, expected);
      case "!=":
        return !equals(actual, expected);
      case ">":
        return compareNumeric(actual, expected) > 0;
      case "<":
        return compareNumeric(actual, expected) < 0;
      case "==contains":
        return contains(actual, expected);
      case "==pattern":
        return matchesPattern(actual, expected);
      default:
        return false;
    }
  }

  function andGroupMatches(andGroup, controllerValues) {
    for (var i = 0; i < andGroup.length; i++) {
      var rule = andGroup[i];
      if (!Object.prototype.hasOwnProperty.call(controllerValues, rule.field)) {
        return false;
      }
      if (!ruleMatches(rule, controllerValues[rule.field])) {
        return false;
      }
    }
    return true;
  }

  function choiceMatches(orGroups, controllerValues) {
    for (var i = 0; i < orGroups.length; i++) {
      if (andGroupMatches(orGroups[i], controllerValues)) {
        return true;
      }
    }
    return false;
  }

  function availableChoices(config, controllerValues) {
    var available = [];
    var canonical = config.canonical_choices || [];
    var rules = config.rules || {};

    for (var i = 0; i < canonical.length; i++) {
      var choice = String(canonical[i]);
      if (!Object.prototype.hasOwnProperty.call(rules, choice)) {
        available.push(choice);
        continue;
      }
      if (choiceMatches(rules[choice], controllerValues)) {
        available.push(choice);
      }
    }

    /**
     * @filter cloakwp/conditional_choices/available_values
     */
    if (window.acf && typeof acf.applyFilters === "function") {
      available = acf.applyFilters(
        "cloakwp/conditional_choices/available_values",
        available,
        config,
        controllerValues
      );
    }

    return available;
  }

  function collectControllerKeys(config) {
    var keys = {};
    var rules = config.rules || {};
    Object.keys(rules).forEach(function (choice) {
      (rules[choice] || []).forEach(function (andGroup) {
        (andGroup || []).forEach(function (rule) {
          if (rule && rule.field) {
            keys[rule.field] = true;
          }
        });
      });
    });
    return Object.keys(keys);
  }

  function scoreController(targetField, candidateField) {
    var score = 0;
    var $target = targetField.$el;
    var $candidate = candidateField.$el;

    if ($target.closest(".acf-row").get(0) && $target.closest(".acf-row").is($candidate.closest(".acf-row"))) {
      score += 40;
    }
    if ($target.closest(".layout").get(0) && $target.closest(".layout").is($candidate.closest(".layout"))) {
      score += 30;
    }
    if (
      $target.closest(".acf-block-fields").get(0) &&
      $target.closest(".acf-block-fields").is($candidate.closest(".acf-block-fields"))
    ) {
      score += 20;
    }
    if ($target.closest("form").get(0) && $target.closest("form").is($candidate.closest("form"))) {
      score += 10;
    }
    if ($target.closest(".acf-fields").get(0) && $target.closest(".acf-fields").is($candidate.closest(".acf-fields"))) {
      score += 5;
    }

    return score;
  }

  function resolveController(targetField, controllerKey, controllerName) {
    var candidates = [];

    if (window.acf && typeof acf.getFields === "function") {
      candidates = acf.getFields({
        key: controllerKey,
        sibling: targetField.$el,
      });

      // Fall back to name lookup when compiled keys disagree with live field keys
      // (common for ACF blocks where parent is group_* but keys were hashed from block_*).
      if ((!candidates || !candidates.length) && controllerName) {
        candidates = acf.getFields({
          name: controllerName,
          sibling: targetField.$el,
        });
      }
    }

    if ((!candidates || !candidates.length) && window.acf && typeof acf.getField === "function") {
      var single = acf.getField(controllerKey);
      if (single) {
        candidates = [single];
      }
    }

    if (!candidates || !candidates.length) {
      return null;
    }

    candidates.sort(function (a, b) {
      return scoreController(targetField, b) - scoreController(targetField, a);
    });

    var resolved = candidates[0];

    /**
     * @filter cloakwp/conditional_choices/resolve_controller
     */
    if (window.acf && typeof acf.applyFilters === "function") {
      resolved = acf.applyFilters(
        "cloakwp/conditional_choices/resolve_controller",
        resolved,
        targetField,
        controllerKey,
        candidates
      );
    }

    return resolved || null;
  }

  function captureCanonical(field) {
    var type = field.get("type");
    var $input = field.$el.find(".acf-input").first();

    if (type === "select") {
      return $input.find("select").first().children().clone(true, true);
    }

    // radio / checkbox / button_group
    return $input.children().clone(true, true);
  }

  function valuesEqual(a, b) {
    return a.join("\0") === b.join("\0");
  }

  function isMultiValueField(field) {
    return field.get("type") === "checkbox" || !!field.get("multiple");
  }

  function getDefaultValues(field) {
    var def = field.get("default_value");
    if (def === null || typeof def === "undefined" || def === false || def === "") {
      var dataDefault = field.$el.attr("data-default");
      if (typeof dataDefault === "undefined" || dataDefault === null || dataDefault === "") {
        return [];
      }
      def = dataDefault;
    }
    return normalizeValue(def);
  }

  function fallbackValues(field, available) {
    var defaults = getDefaultValues(field).filter(function (value) {
      return available.indexOf(value) !== -1;
    });

    if (isMultiValueField(field)) {
      return defaults;
    }

    if (defaults.length) {
      return [defaults[0]];
    }

    // Last resort: first currently available choice (avoids an empty radio group).
    return available.length ? [available[0]] : [];
  }

  function setFieldValues(field, values) {
    if (isMultiValueField(field)) {
      field.val(values);
      return;
    }
    field.val(values.length ? values[0] : null);
  }

  /**
   * Keep the field on a valid choice when availability changes:
   * - Remember values that were selected when they became hidden
   * - Fall back to the field default (or first available) instead of wiping
   * - Restore remembered values when they become available again
   *
   * @param {string[]} selectedSnapshot values from before DOM toggles (which may uncheck radios)
   */
  function reconcileSelection(field, state, available, selectedSnapshot) {
    if (!state.deferred) {
      state.deferred = [];
    }

    // Prefer the pre-toggle snapshot — applyToggleChoices unchecks hidden radios, which
    // would make field.val() look empty and skip default/restore logic.
    var selected = Array.isArray(selectedSnapshot)
      ? selectedSnapshot.slice()
      : normalizeValue(field.val());
    var live = normalizeValue(field.val());
    var multi = isMultiValueField(field);

    // Prefer restoring previously-selected conditional choices that are visible again.
    var restorable = state.deferred.filter(function (value) {
      return available.indexOf(value) !== -1;
    });

    if (restorable.length) {
      var restored;
      if (multi) {
        restored = selected.filter(function (value) {
          return available.indexOf(value) !== -1;
        });
        restorable.forEach(function (value) {
          if (restored.indexOf(value) === -1) {
            restored.push(value);
          }
        });
      } else {
        restored = [restorable[0]];
      }

      state.deferred = state.deferred.filter(function (value) {
        return restorable.indexOf(value) === -1;
      });

      if (!valuesEqual(live, restored)) {
        setFieldValues(field, restored);
        return true;
      }
      return false;
    }

    var kept = [];
    var removed = [];
    selected.forEach(function (value) {
      if (available.indexOf(value) !== -1) {
        kept.push(value);
      } else {
        removed.push(value);
      }
    });

    // Heal when the pre-toggle selection lost choices, or the live value is now invalid
    // (e.g. applyToggleChoices unchecked the only selected radio).
    var liveInvalid =
      live.length === 0
        ? removed.length > 0
        : live.every(function (value) {
            return available.indexOf(value) === -1;
          });
    var needsFallback = removed.length > 0 || liveInvalid;

    if (!needsFallback) {
      return false;
    }

    removed.forEach(function (value) {
      if (state.deferred.indexOf(value) === -1) {
        state.deferred.push(value);
      }
    });

    var next = kept.length ? kept : fallbackValues(field, available);

    // Don't treat "empty → empty" as progress when there is still nothing available.
    if (!next.length && !live.length) {
      return false;
    }

    if (valuesEqual(live, next)) {
      return false;
    }

    setFieldValues(field, next);
    return true;
  }

  function applySelectChoices(field, state, available) {
    var $select = field.$el.find("select").first();
    if (!$select.length) return;

    var $fragment = jQuery(document.createDocumentFragment());
    state.canonical.each(function () {
      var $node = jQuery(this).clone(true, true);
      if ($node.is("optgroup")) {
        var $group = $node.clone().empty();
        $node.children("option").each(function () {
          var $option = jQuery(this);
          var value = String($option.attr("value"));
          if ($option.is("[disabled]") && value === "") {
            $group.append($option.clone(true, true));
            return;
          }
          if (available.indexOf(value) !== -1) {
            $group.append($option.clone(true, true));
          }
        });
        if ($group.children().length) {
          $fragment.append($group);
        }
        return;
      }

      if ($node.is("option")) {
        var value = String($node.attr("value"));
        var isPlaceholder = $node.is("[disabled]") || value === "";
        if (isPlaceholder || available.indexOf(value) !== -1) {
          $fragment.append($node);
        }
      }
    });

    $select.empty().append($fragment);
  }

  function applyToggleChoices(field, available) {
    // Radio/checkbox render as ul>li; button_group as labeled inputs. Target each
    // input's option wrapper — not `.acf-input`'s direct children (often a single ul).
    field.$el
      .find(".acf-input input[type='radio'], .acf-input input[type='checkbox']")
      .each(function () {
        var $input = jQuery(this);
        var value = String($input.attr("value"));
        var allowed = available.indexOf(value) !== -1;
        var $wrap = $input.closest("li, .acf-button-group label, label");
        if (!$wrap.length) {
          $wrap = $input.parent();
        }
        $wrap.toggleClass("cloakwp-conditional-choice-hidden", !allowed);
        $wrap.css("display", allowed ? "" : "none");
        if (!allowed) {
          $input.prop("checked", false);
        }
      });
  }

  function controllerNamesByKey(config) {
    var map = {};
    var rules = config.rules || {};
    Object.keys(rules).forEach(function (choice) {
      (rules[choice] || []).forEach(function (andGroup) {
        (andGroup || []).forEach(function (rule) {
          if (rule && rule.field && rule.name) {
            map[rule.field] = rule.name;
          }
        });
      });
    });
    return map;
  }

  function readControllerValues(targetField, controllerKeys, config) {
    var values = {};
    var missing = [];
    var names = controllerNamesByKey(config || {});

    controllerKeys.forEach(function (key) {
      var controller = resolveController(targetField, key, names[key]);
      if (!controller) {
        missing.push(key);
        return;
      }
      // Index by compiled key so rule evaluation keeps working even when the
      // controller was resolved via name fallback.
      values[key] = controller.val();
      var liveKey = controller.get("key");
      if (liveKey && liveKey !== key) {
        values[liveKey] = values[key];
      }
    });

    return { values: values, missing: missing };
  }

  function indexController(targetField, key) {
    if (!key) return;
    if (!controllerIndex[key]) {
      controllerIndex[key] = [];
    }
    if (controllerIndex[key].indexOf(targetField) === -1) {
      controllerIndex[key].push(targetField);
    }
  }

  function indexControllers(targetField, controllerKeys, config) {
    var names = controllerNamesByKey(config || {});

    controllerKeys.forEach(function (key) {
      indexController(targetField, key);

      // Also index under the live field key so change events re-run updates when
      // compiled controller keys disagree with ACF's registered keys.
      var controller = resolveController(targetField, key, names[key]);
      if (controller) {
        indexController(targetField, controller.get("key"));
      }
    });
  }

  function enqueueUpdate(field) {
    if (queued && queued.has(field)) {
      return;
    }
    if (queued) {
      queued.add(field);
    }
    queue.push(field);
    if (!flushing) {
      flushing = true;
      Promise.resolve().then(flushQueue);
    }
  }

  function flushQueue() {
    var pass = 0;
    while (queue.length && pass < 8) {
      var batch = queue.splice(0, queue.length);
      if (queued) {
        batch.forEach(function (field) {
          queued.delete(field);
        });
      }
      batch.forEach(updateField);
      pass += 1;
    }
    flushing = false;
    if (queue.length) {
      console.warn(
        "[cloakwp/conditional-choices] Dependency updates did not fully settle; remaining queue skipped."
      );
      queue.length = 0;
    }
  }

  function updateField(field) {
    if (!states || !states.has(field)) {
      return;
    }

    var state = states.get(field);
    if (state.updating) {
      return;
    }

    state.updating = true;

    try {
      var resolved = readControllerValues(field, state.controllerKeys, state.config);
      if (resolved.missing.length) {
        console.warn(
          "[cloakwp/conditional-choices] Missing controller field(s):",
          resolved.missing.join(", "),
          "for",
          field.get("name")
        );
      }

      var available = availableChoices(state.config, resolved.values);
      var availableKey = available.join("\0");
      // Snapshot before DOM toggles — hiding radios unchecks them and clears field.val().
      var selectedSnapshot = normalizeValue(field.val());

      if (state.lastAvailable !== availableKey) {
        if (window.acf && typeof acf.doAction === "function") {
          acf.doAction("cloakwp/conditional_choices/before_update", field, available, state.config);
        }

        if (field.get("type") === "select") {
          applySelectChoices(field, state, available);
        } else {
          applyToggleChoices(field, available);
        }

        state.lastAvailable = availableKey;

        if (window.acf && typeof acf.doAction === "function") {
          acf.doAction("cloakwp/conditional_choices/after_update", field, available, state.config);
        }
      }

      var valueChanged = reconcileSelection(
        field,
        state,
        available,
        selectedSnapshot
      );
      if (valueChanged) {
        field.$el.trigger("change");
      }
    } finally {
      state.updating = false;
    }
  }

  function initField(field) {
    if (!field || !field.$el || !field.$el.length) {
      return;
    }

    if (states && states.has(field)) {
      enqueueUpdate(field);
      return;
    }

    var config = parseConfig(field.$el);
    if (!config || !config.rules) {
      return;
    }

    var controllerKeys = collectControllerKeys(config);
    var state = {
      config: config,
      controllerKeys: controllerKeys,
      canonical: captureCanonical(field),
      deferred: [],
      lastAvailable: null,
      updating: false,
    };

    if (states) {
      states.set(field, state);
    }

    indexControllers(field, controllerKeys, config);
    enqueueUpdate(field);
  }

  function onControllerChange(field) {
    if (!field) return;
    var key = field.get("key");
    var dependents = controllerIndex[key] || [];
    dependents.forEach(enqueueUpdate);
  }

  function bindControllerListener(field) {
    if (!field || field.__cloakwpConditionalChoicesBound) {
      return;
    }
    field.__cloakwpConditionalChoicesBound = true;
    field.on("change", function () {
      onControllerChange(field);
    });
  }

  function boot() {
    if (!window.acf || typeof acf.addAction !== "function") {
      return false;
    }

    acf.addAction("new_field", function (field) {
      bindControllerListener(field);
      initField(field);
    });
    acf.addAction("ready_field", initField);
    acf.addAction("append_field", initField);
    acf.addAction("show_field", initField);
    if (typeof acf.addAction === "function") {
      acf.addAction("change_field", onControllerChange);
    }
    acf.addAction("select2_init", function ($select, args, settings, field) {
      initField(field);
    });

    // Defensive scan for already-rendered fields.
    acf.addAction("ready", function ($el) {
      ($el || jQuery(document))
        .find("[" + ATTR + "]")
        .each(function () {
          var field = acf.getField(jQuery(this));
          initField(field);
        });
    });

    return true;
  }

  if (!boot()) {
    var attempts = 0;
    var timer = setInterval(function () {
      attempts += 1;
      if (boot() || attempts > 40) {
        clearInterval(timer);
      }
    }, 250);
  }
})();
