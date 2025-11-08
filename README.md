# Conference Timetable Plugin - Version 2.0.0

## What's New - All Your Requested Features!

### 1. ✅ Info Blocks Are Now Independent
**Problem Fixed:** Info blocks no longer interfere with event positioning!

- Info blocks now have a **lower z-index (5)** compared to events (10+)
- Events will always render **on top** of info blocks
- When you add an 8:00 AM event and there's an 8:30 AM info block, the event will display correctly at 8:00 AM
- Info blocks are purely informational and don't affect the timetable grid

**How it works:**
- Info blocks use `position: absolute` with `z-index: 5`
- Events use `z-index: 10+` 
- Events are rendered AFTER info blocks in the DOM
- `pointer-events: none` on info blocks means they can't be clicked

---

### 2. ✅ Controllable Info Block Height
**New Feature:** You can now set custom heights for info blocks!

- New field: **Height (pixels)** in the Info Block metabox
- Default: 60px (one row)
- You can set any height: 30px, 90px, 120px, etc.
- Perfect for creating different sized info sections

**Example Usage:**
- Lunch break: 90px height
- Short announcement: 30px height
- Major session divider: 120px height

---

### 3. ✅ Elementor Dynamic Tags Support
**New Feature:** All plugin fields are now accessible via Elementor dynamic tags!

The plugin now registers a custom tag group called **"Conference Timetable Fields"** with these tags:

**Available Dynamic Tags:**
1. Event Title
2. Event Date
3. Event Start Time
4. Event End Time
5. Event Speaker
6. Event Room/Location
7. Event Track
8. Event Content
9. Organizer Name
10. Organizer Image (for image widgets)
11. Event Categories

**How to Use in Elementor:**
1. Create a loop template for `ctt_event` post type
2. Add a heading/text widget
3. Click the dynamic tag icon
4. Select **Conference Timetable Fields** group
5. Choose the field you want to display

**Example Loop Builder Setup:**
```
Query: Post Type = ctt_event
Template:
  - Image Widget → Dynamic Tag: Organizer Image
  - Heading → Dynamic Tag: Event Title
  - Text → Dynamic Tag: Event Speaker
  - Text → Dynamic Tag: Event Start Time - Event End Time
  - Text → Dynamic Tag: Event Categories
```

---

### 4. ✅ Event Categories Taxonomy
**New Feature:** Categorize your events with a custom taxonomy!

- New taxonomy: **Event Categories**
- Hierarchical (like regular categories)
- Shows in admin columns
- Available in REST API
- Accessible via Elementor dynamic tags

**How to Use:**
1. Go to **Timetable → Categories** in WordPress admin
2. Create categories like:
   - Keynote
   - Workshop
   - Panel Discussion
   - Networking
   - Technical Session
3. Assign categories to events
4. Use in Elementor loops or filters

**API Response includes categories:**
```json
{
  "id": 123,
  "title": "AI in Healthcare",
  "categories": ["Keynote", "Technical Session"],
  ...
}
```

---

## Installation

1. **Backup your current plugin** (just in case)
2. Replace these files:
   - `conference-timetable-updated.php` (main plugin file)
   - `ctt-script.js` (JavaScript)
   - `ctt-styles.css` (no changes, but included)
3. **NEW FILE:** Add `elementor-tags.php` to the same directory
4. Deactivate and reactivate the plugin to flush rewrite rules

---

## File Structure

```
Conference Timetable/
├── conference-timetable-updated.php (Main plugin file)
├── elementor-tags.php (NEW - Elementor integration)
├── ctt-script.js (Updated - independent info blocks)
└── ctt-styles.css (No changes)
```

---

## Updated Features Summary

### Info Block Metabox Now Has:
- Date
- Position (HH:MM) - snaps to grid
- **Height (pixels)** ← NEW
- Time Label ← For left column display
- Track Start
- Track End
- Background Color
- Text Color
- Additional Content

### Event Categories:
- Accessible in WordPress admin under **Timetable → Categories**
- Shows in admin columns
- Available in REST API
- Elementor dynamic tag support

### Elementor Integration:
- 11 dynamic tags for all event fields
- Works with loop builder
- Custom tag group for easy access
- Image tag for organizer photos

---

## Usage Examples

### Example 1: Creating a Lunch Break Info Block
1. Go to **Timetable → Info Blocks → Add New**
2. Title: "Lunch Break"
3. Date: Select conference date
4. Position: 12:00
5. Height: 90 (pixels)
6. Time Label: "Lunch"
7. Track Start: 1
8. Track End: 6 (spans all tracks)
9. Background Color: #fef3c7
10. Text Color: #78350f
11. Save

Result: A 90px tall lunch break banner spanning all tracks at 12:00 PM

### Example 2: Creating an Elementor Event Loop
1. Create a new template in Elementor
2. Add Loop Grid
3. Query: Post Type = ctt_event
4. Add these widgets in each loop item:
   - **Heading**: Dynamic → Event Title
   - **Text**: Dynamic → Event Date
   - **Text**: Dynamic → Event Start Time - Event End Time
   - **Text**: Dynamic → Event Speaker
   - **Text**: Dynamic → Event Room/Location
   - **Text**: Dynamic → Event Categories
   - **Image**: Dynamic → Organizer Image

### Example 3: Filtering Events by Category
```php
// In your theme or custom plugin
$args = array(
    'post_type' => 'ctt_event',
    'tax_query' => array(
        array(
            'taxonomy' => 'ctt_event_category',
            'field'    => 'slug',
            'terms'    => 'workshop',
        ),
    ),
);
$query = new WP_Query($args);
```

---

## Technical Changes

### JavaScript (ctt-script.js)
- Info blocks now render with `z-index: 5` (below events)
- Events maintain `z-index: 10+` (above info blocks)
- Window calculation now uses ONLY events, not info blocks
- Custom height from meta field is applied to info blocks

### PHP (conference-timetable-updated.php)
- Added taxonomy registration: `ctt_event_category`
- Added `_ctt_info_height` meta field
- REST API now includes categories in event data
- New Elementor integration hook
- Made post type public with `show_in_rest => true`

### Elementor Tags (elementor-tags.php) - NEW FILE
- 11 custom dynamic tags
- Custom tag group registration
- Image tag for organizer photos
- Support for loops and templates

---

## Troubleshooting

**Info blocks still overlapping events?**
- Clear your browser cache
- Make sure you're using the new JavaScript file
- Check browser console for errors

**Elementor tags not showing?**
- Make sure Elementor is installed and active
- Deactivate and reactivate the plugin
- Clear Elementor cache

**Categories not showing?**
- Deactivate and reactivate the plugin
- Go to Settings → Permalinks and click Save

---

## Compatibility

- WordPress 5.0+
- Elementor 3.0+ (for dynamic tags)
- PHP 7.4+

---

## Support

If you encounter any issues or need customization, you can:
1. Check the browser console for JavaScript errors
2. Enable WordPress debug mode to see PHP errors
3. Test with a default theme to rule out theme conflicts

---

## Changelog

### Version 2.0.0
- ✅ Info blocks now independent from time grid
- ✅ Added custom height control for info blocks
- ✅ Added Elementor dynamic tags support
- ✅ Added event categories taxonomy
- ✅ Made post types REST API ready
- ✅ Improved z-index layering system
- ✅ Fixed event positioning with info blocks

### Version 1.5.1
- Initial version with basic timetable functionality
