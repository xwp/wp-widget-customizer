=== Widget Customizer ===
Contributors:      X-team, westonruter, shaunandrews, michael-arestad, johnregan3, akeda, topher1kenobe, topquarky, bobbravo2, ricardocorreia
Tags:              customizer, widgets, sidebars, preview
Requires at least: 3.7
Tested up to:      3.8.1
Stable tag:        trunk
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Edit widgets and preview changes in Theme Customizer, with a control for each widget form in sections added for each sidebar rendered in the preview.

== Description ==

**This plugin is being developed as part of the Widgets UI Refresh feature-as-plugin group. Read the [Widget Customizer Feature-as-Plugin Merge Proposal](http://make.wordpress.org/core/2014/01/28/widget-customizer-feature-as-plugin-merge-proposal/).**

***New:*** This plugin has been merged into WordPress Core! See [r27419](https://core.trac.wordpress.org/changeset/27419). This plugin will deactivate itself when WordPress is updated to this revision.

**Notice regarding empty sidebars:** Unless you are running trunk, you won't be able to add widgets to *empty* sidebars. This is because the [temporary hooks](http://core.trac.wordpress.org/ticket/25368) necessary are [removed in final releases](http://core.trac.wordpress.org/changeset/25878/branches/3.7/src/wp-includes/widgets.php). So you must currently add at least one widget to each sidebar (in the traditional way) for it to appear in the customizer.

Widgets in WordPress provide an easy way to add functionality to predefined areas of your theme templates. However, once you add a widget to a sidebar you have to leave the WordPress admin to go back to the frontend to actually see how the updated widget appears in the sidebar on your site's public frontend. While you are making these changes and experimenting with a widget, it could be completely broken and everyone visiting your site will see this broken widget since there is no core way to preview changes made to widgets. But WordPress also provides an excellent way to preview changes to various settings on your site via the (Theme) Customizer. Changes made when using the Customizer are not visible to site visitors until you hit Save & Publish. So what if widgets could be edited in the Customizer? That's what this plugin makes possible.

Each registered sidebar on your site gets its own section in the Customizer panel. Within each Sidebar Widgets section, each widget added to the sidebar will appear in order and its widget form will appear there just as it appears when editing widgets in the WordPress widgets admin page. Upon making a change to the widget form, press the form's Apply button to then see the changes in the preview window and to stage the widget changes for committing once the **Save & Publish** button is clicked. Again, changes made when in the Customizer do not appear until you hit this button. This goes for whether you're adding a new widget, editing existing widgets, reordering widgets, dragging widgets to other sidebars, or even removing widgets from the sidebars entirely: all of these actions are previewable.

When you remove a widget from a sidebar, it is not deleted. Instead, it is moved from an active sidebar to the "Inactive Widgets" sidebar which can currently be seen on the widgets admin page. As such, removing a widget now is the same as trashing a widget.

Customizer control sections for sidebars will be shown or hidden dynamically when the the preview window is initially loaded or when navigating the site within the preview window, based on whether or not the sidebar got rendered in the previewed page. Only sidebars which can be previewed will be shown in the customizer panel.

While all themes and widgets can work with Widget Customizer, for the best experience the themes and widgets need to indicate they support live previews of widgets. Without such support added, each change to a sidebar or widget will result in the preview window being refreshed, resulting in a delay before the changes can be seen. See Read more about §[Live Previews](http://wordpress.org/plugins/widget-customizer/other_notes/#Live-Previews).

*No longer do you have to edit your widgets blind!*

[youtube http://www.youtube.com/watch?v=D1GHc5OGWEQ]

And here's an **awesome bonus**: since the widgets are registered as settings in the customizer, if you also have the [Settings Revisions](http://wordpress.org/plugins/settings-revisions/) plugin also activated, the widgets will then get versioned! Each time you save your changes, the current instance of each widget will be saved in a revision, and you can restore a previous widget state by rolling back the settings revision.

**Development of this plugin is done [on GitHub](https://github.com/x-team/wp-widget-customizer). Pull requests welcome. Please see [issues](https://github.com/x-team/wp-widget-customizer/issues) reported there before going to the plugin forum.**

== Live Previews ==

While all themes and widgets can work with Widget Customizer, by default each change to a sidebar or widget will result in the preview window being refreshed (settings default to `transport=refresh`), resulting in a delay before the changes can be seen. As of v0.10, changes to sidebars and widgets no longer require a full page refresh of the preview window in order to see the changes applied. To enable a much more responsive preview experience, themes and widgets must indicate that they support Widget Customizer live previews (which will, in part, add `transport=postMessage` for the relevant settings).

All core widgets and themes distributed with WordPress core are supported by default. For other themes, simply add `add_theme_support( 'widget-customizer' );` in your theme's `functions.php` to opt-in. If your theme does some dynamic layout for a sidebar (like Twenty Thirteen uses jQuery Masonry), you'll also need to then enqueue some JavaScript to listen for changes to the sidebar and reflow them when that happens; see the [bundled support](https://github.com/x-team/wp-widget-customizer/blob/master/theme-support/twentythirteen.js) for Twenty Thirteen to see an example of what is required.

Along with a themes needing to indicate support for live-previewable sidebars, widgets must also indicate that they support being live-previewed with Widget Customizer. When updating a widget, an Ajax call is made to re-render the widget with the latest changes, and then the widget element is replaced in the sidebar inside the preview. If a widget is purely static HTML with no associated script behaviors or dynamic stylesheets (like all widgets in core), then they can right-away indicate support for live previews simply by including `add_filter( 'customizer_widget_live_previewable_{id_base}', '__return_true' );`. As with sidebars, if a widget has dynamic behaviors which normally only get added when the page first loads (e.g. such as a widget which includes a carousel) , then a script needs to be enqueued in the Customizer preview which will re-initialize the widget when a widget is changed.

The `sidebar-updated` and `widget-updated` events get triggered on `wp.customize` when sidebars and widgets get updated respectively, each being passed the sidebar ID and the widget ID respectively as the first argument in the callbacks. For a full example demonstrating how to add theme support for live-previewing dynamic sidebars and how to add support for JS-initialized widgets, see this [annotated Gist](https://gist.github.com/westonruter/7965203).

== Screenshots ==

1. Before any changes have been made; widgets appear in sections corresponding to their assigned sidebars
2. After a change is made, the widget's Apply button is pressed to then see changes in preview and so changes can be published
3. Widgets can be added in sidebar sections; widgets get added to the top, and can be dragged into the desired location
4. Widgets can be trashed while in customizer, causing them to be moved to the Inactive Widgets sidebar
5. Widgets are be rearranged by drag-and-drop, and widgets can be assigned to other sidebars by dragging them over
6. Browse available widgets to add via a panel that slides out
7. I DON'T ALWAYS TEST MY WORDPRESS WIDGETS IN PRODUCTION, BUT WHEN I DO I USE THE WIDGET CUSTOMIZER PLUGIN

== Changelog ==

### 0.15.1 ###
* As of [r27419](https://core.trac.wordpress.org/changeset/27419) in WordPress Core trunk, the functionality in this plugin has been merged into core! This plugin will deactivate itself when WordPress is updated to this revision.

= 0.15 =
* Add support for wide widget controls by sliding them out horizontally over the preview. Fixes [#18](https://github.com/x-team/wp-widget-customizer/issues/18). PR [#89](https://github.com/x-team/wp-widget-customizer/pull/89). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Eliminate Update button and so preview updates with each input change for widgets that support live previews. Fixes [#45](https://github.com/x-team/wp-widget-customizer/issues/45). PR [#93](https://github.com/x-team/wp-widget-customizer/issues/93). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Make widget form controls more compact on smaller screen resolutions. PR [#88](https://github.com/x-team/wp-widget-customizer/pull/88). Props [michael-arestad](http://profiles.wordpress.org/michael-arestad/).
* Improve styling of widget search field. Fixes [#83](https://github.com/x-team/wp-widget-customizer/issues/83). Props [shaunandrews](http://profiles.wordpress.org/shaunandrews/).
* Rename "Update" button to "Apply". PR [#80](https://github.com/x-team/wp-widget-customizer/pull/80). Props [arnoesterhuizen](http://profiles.wordpress.org/arnoesterhuizen/).
* Prevent error when initializing sidebar containing unregistered widget. Fixes [#91](https://github.com/x-team/wp-widget-customizer/issues/91). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Only show one widget form control expanded at a time. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Eliminate use of `filter_input()`. PR [#74](https://github.com/x-team/wp-widget-customizer/pull/74). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Add live preview support for Twenty Fourteen Ephemera Widget.
* And [other changes](https://github.com/x-team/wp-widget-customizer/compare/0.14...0.15)...

= 0.14 =
* Add keyboard-accessible means of reordering widgets. Fixes [#21](https://github.com/x-team/wp-widget-customizer/pull/21). Props [michael-arestad](http://profiles.wordpress.org/michael-arestad/).
* Allow movement of widgets to other sidebars to be live-previewed (not needing page refresh). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Add widget icon defaults. PR [#75](https://github.com/x-team/wp-widget-customizer/pull/75). Props [michael-arestad](http://profiles.wordpress.org/michael-arestad/).
* Bugfix for handling keypress in available widgets panel. PR [#72](https://github.com/x-team/wp-widget-customizer/pull/72). Props [knishiura-lab](https://github.com/knishiura-lab).
* Add theme support for Twenty Fourteen. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* And [other changes](https://github.com/x-team/wp-widget-customizer/compare/0.13...0.14)...

= 0.13 =
* Widget addition panel now features the use of a vector font for the widget icons instead of using PNGs. Fixes [#69](https://github.com/x-team/wp-widget-customizer/issues/69). Props [michael-arestad](http://profiles.wordpress.org/michael-arestad/).
* Fade widgets not rendered in sidebars on currently-previewed URL (very helpful when using Jetpack's Widget Visibility or the like). Fixes [#65](https://github.com/x-team/wp-widget-customizer/issues/65). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Sanitize all settings, including widget instances and sidebar manifests. Serialize widget instances using PHP's `serialize` instead of using JSON, since there may be values which cannot be represented in JSON. Ensure that backslashes are not dropped from widget instances. Fixes [#28](https://github.com/x-team/wp-widget-customizer/issues/28). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Wrap `available-widgets` in `widgets-left` for compatibility with plugins which look for widget templates in that element. Fixes [#51](https://github.com/x-team/wp-widget-customizer/issues/51). Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Fix rendering widgets adjacent to hidden widgets. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Improve handling of widget updates which yield no instance changes. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Add temp filter to JS-sanitize values for settings which are no longer extant or if they need to be sanitized in an environment where the customizer is not initialized; done specifically for the [Settings Revisions](http://wordpress.org/plugins/settings-revisions/) plugin. Props [westonruter](http://profiles.wordpress.org/westonruter/).

= 0.12 =
* Delete widgets directly if they have not been previously-saved; if a widget has been previously saved, removal of the widget moves it to the inactive widgets sidebar. Fixes <a href="https://github.com/x-team/wp-widget-customizer/issues/46">#46</a>. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Facilitate widgets opting-in to indicate customizer support via a widget option passed via constructor; this is in addition to the filter method. Fixes <a href="https://github.com/x-team/wp-widget-customizer/issues/67">#67</a>. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Auto-open sidebar section in customizer when accessed via notice on widgets admin. Fixes <a href="https://github.com/x-team/wp-widget-customizer/issues/32">#32</a>. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Animate toggling of active/inactive sidebars. Fixes <a href="https://github.com/x-team/wp-widget-customizer/issues/9">#9</a>. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Hide sidebar sections initially to prevent FOIS (flash of inactive sidebars). Fixes <a href="https://github.com/x-team/wp-widget-customizer/issues/36">#36</a>. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Close available widgets panel when previewed URL changes. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Remove default live preview support from children of core twenty* themes. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Fix submission of selected widget with “enter” keypress on filter input. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Fix top widget padding and broken borders. Props [westonruter](http://profiles.wordpress.org/westonruter/).

= 0.11 =
Introduce new panel for browsing and selecting widgets to add to a sidebar. This replaces the select dropdown that appeared at the top of the sidebar's widget controls. Props [shaunandrews](http://profiles.wordpress.org/shaunandrews/), [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#58](https://github.com/x-team/wp-widget-customizer/pull/58).

= 0.10.1 =
* Require shift key when clicking on a widget in the preview to open and focus on the widget in the customizer. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Prevent edge case error where `dynamic_sidebar` is called for a non-registered sidebar. Props [westonruter](http://profiles.wordpress.org/westonruter/).

= 0.10 =
Allow themes and widgets to support previewing changes to sidebars and widgets without resorting to refreshing the entire preview window. Props [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#37](https://github.com/x-team/wp-widget-customizer/pull/37).

= 0.9.6 =
Skip over instances for widgets no longer registered (as core does), eliminating assertion warnings. Props [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#48](https://github.com/x-team/wp-widget-customizer/issues/48).

= 0.9.5 =
Fix padding for widget customizer controls in WordPress 3.8. Props [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#57](https://github.com/x-team/wp-widget-customizer/issues/57).

= 0.9.4 =
* Add demo video to readme. Props [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/).
* Apply some jshint fixes and update plugin dev library

= 0.9.3 =
* Fix HTML markup breaking customizer "Collapse" link position.
* Trigger widget update when hitting enter in control input. Fixes [#47](https://github.com/x-team/wp-widget-customizer/issues/47).

= 0.9.2 =
Fix addition of previously-uninstantiated widgets to previously-empty sidebars. It was not possible to add new widgets to a fresh install. Props [westonruter](http://profiles.wordpress.org/westonruter/).

= 0.9.1 =
Render widget control templates into DOM for plugins to manipulate. The Jetpack Widget Visibility module expects the widget templates to be rendered into the DOM as hidden elements so that it can inject the "Visibility" button in the proper place. So we have to move the templates from the model and into the DOM for compat. Other plugins probably do this as well. Props [westonruter](http://profiles.wordpress.org/westonruter/).

= 0.9 =
* Add support for adding new widgets in the customizer. Widgets can now be added, reordered, modified, and deleted all in the customizer preview, without impacting any visitor to the site until you hit **Save & Publish**. Props [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#3](https://github.com/x-team/wp-widget-customizer/issues/3).
* Widgets in the customizer are now all properly model-driven, meaning that changes to the settings will trigger changes to the controls. This ensures that the [Settings Revisions](wordpress.org/plugins/settings-revisions/) can work as expected, even allowing you to restore previously trashed widgets and restore previous widget orderings and sidebar placements. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Improve compatibility with plugins that add and extend widgets; the Jetpack Widget Visibility module now works fully in the customizer.  Props [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#39](https://github.com/x-team/wp-widget-customizer/issues/39).
* Change widget button from primary **Save** to secondary **Apply** button. Props [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#40](https://github.com/x-team/wp-widget-customizer/issues/40).
* Attempt to prevent widgets from forcing their controls to be wide. Props [westonruter](http://profiles.wordpress.org/westonruter/). See [#18](https://github.com/x-team/wp-widget-customizer/issues/18).
* Update styles for compatibility with MP6. Props [westonruter](http://profiles.wordpress.org/westonruter/). See [#33](https://github.com/x-team/wp-widget-customizer/issues/33).
* Add initial unit tests. Props [akeda](http://profiles.wordpress.org/akeda/) (gedex). Fixes [#25](https://github.com/x-team/wp-widget-customizer/issues/25).

= 0.8 =
* Remove (trash) widgets from sidebars in the customizer and preview their removal before publishing the change. Removed widgets are moved to the Inactive Widgets sidebar accessible on the widgets admin page. Props [topquarky](http://profiles.wordpress.org/topquarky/) (TrevorMills), [westonruter](http://profiles.wordpress.org/westonruter/). Fixes [#22](https://github.com/x-team/wp-widget-customizer/issues/22).
* Add keyboard-accessible way to expand/collapse widget form controls. Props [topquarky](http://profiles.wordpress.org/topquarky/) (TrevorMills). Fixes [#26](https://github.com/x-team/wp-widget-customizer/issues/26).
* Add move cursor when hovering over widget form controls. Props [westonruter](http://profiles.wordpress.org/westonruter/).
* Feature link to Customizer on the Widgets admin page. Props [johnregan3](http://profiles.wordpress.org/johnregan3/). Fixes [#29](https://github.com/x-team/wp-widget-customizer/issues/29).

= 0.7 =
* Make detection of sidebars in previewed template more robust by integrating new temp hooks available in trunk. Fixes [#15](https://github.com/x-team/wp-widget-customizer/pull/15).
* Allow widget form controls to be dragged to other sidebar sections. Fixes [#24](https://github.com/x-team/wp-widget-customizer/pull/24).
* Eliminate duplicated code by re-using `wp_widget_control()`
* Add PHPCS, JSHint, and Travis CI integration

= 0.6 =
Add drag-and-drop reordering of customizer controls, where the new order is itself previewed and is persisted until the settings are saved. Fixes issue [#1](https://github.com/x-team/wp-widget-customizer/pull/1). Props [bobbravo2](http://profiles.wordpress.org/bobbravo2/), [westonruter](http://profiles.wordpress.org/westonruter/).

= 0.5 =
Hovering over widgets in preview highlights corresponding customizer sections and controls in panel. Clicking a widget in preview opens widget form in panel and focuses on first input. Interacting with widget form highlights widget in preview. Note that this issue resolves a major usability problem illustrated by the [user test video](http://make.wordpress.org/ui/2013/09/18/widgets-sept-16-chat-notes/#comment-23907). Fixes issue [#5](https://github.com/x-team/wp-widget-customizer/pull/5). Props [ricardocorreia](http://profiles.wordpress.org/ricardocorreia/), [westonruter](http://profiles.wordpress.org/westonruter/).

= 0.4 =
Render widget form controls in a collapsed state (with a toggle) as on the widgets admin page; add in-widget-title ([#7](https://github.com/x-team/wp-widget-customizer/issues/7)). Props [johnregan3](http://profiles.wordpress.org/johnregan3/).

= 0.3 =
* Add banner image ([#10](https://github.com/x-team/wp-widget-customizer/issues/10)). Props [johnregan3](http://profiles.wordpress.org/johnregan3/).
* Eliminate warning with an array type check

= 0.2 =
Only show customizer sections for sidebars which can currently be seen in the preview; sections show/hide dynamically as the preview frame is navigated.

= 0.1 =
First Release
