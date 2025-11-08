<?php
/**
 * Elementor Dynamic Tags for Conference Timetable Plugin
 * 
 * This file contains all custom dynamic tags that make
 * Conference Timetable fields accessible in Elementor
 */

if (!defined('ABSPATH')) exit;

// Base Tag Class
abstract class CTT_Base_Tag extends \Elementor\Core\DynamicTags\Tag {
    public function get_group() {
        return 'ctt-fields';
    }
    
    public function get_categories() {
        return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
    }
}

// Event Title
class CTT_Event_Title_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-title';
    }
    
    public function get_title() {
        return 'Event Title';
    }
    
    public function render() {
        echo get_the_title();
    }
}

// Event Date
class CTT_Event_Date_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-date';
    }
    
    public function get_title() {
        return 'Event Date';
    }
    
    public function render() {
        $date = get_post_meta(get_the_ID(), '_ctt_date', true);
        if ($date) {
            echo date('F j, Y', strtotime($date));
        }
    }
}

// Event Start Time
class CTT_Event_Start_Time_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-start-time';
    }
    
    public function get_title() {
        return 'Event Start Time';
    }
    
    public function render() {
        echo get_post_meta(get_the_ID(), '_ctt_start', true);
    }
}

// Event End Time
class CTT_Event_End_Time_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-end-time';
    }
    
    public function get_title() {
        return 'Event End Time';
    }
    
    public function render() {
        echo get_post_meta(get_the_ID(), '_ctt_end', true);
    }
}

// Event Speaker
class CTT_Event_Speaker_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-speaker';
    }
    
    public function get_title() {
        return 'Event Speaker';
    }
    
    public function render() {
        echo get_post_meta(get_the_ID(), '_ctt_speaker', true);
    }
}

// Event Room
class CTT_Event_Room_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-room';
    }
    
    public function get_title() {
        return 'Event Room/Location';
    }
    
    public function render() {
        echo get_post_meta(get_the_ID(), '_ctt_room', true);
    }
}

// Event Track
class CTT_Event_Track_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-track';
    }
    
    public function get_title() {
        return 'Event Track';
    }
    
    public function render() {
        $track = get_post_meta(get_the_ID(), '_ctt_track', true);
        $span = get_post_meta(get_the_ID(), '_ctt_track_span', true) ?: 1;
        
        if ($span > 1) {
            $end_track = min(6, $track + $span - 1);
            echo 'Track ' . $track . '–' . $end_track;
        } else {
            echo 'Track ' . $track;
        }
    }
}

// Event Content
class CTT_Event_Content_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-content';
    }
    
    public function get_title() {
        return 'Event Content';
    }
    
    public function get_categories() {
        return [
            \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
            \Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
        ];
    }
    
    public function render() {
        echo apply_filters('the_content', get_the_content());
    }
}

// Organizer Name
class CTT_Event_Organizer_Name_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-organizer-name';
    }
    
    public function get_title() {
        return 'Organizer Name';
    }
    
    public function render() {
        echo get_post_meta(get_the_ID(), '_ctt_organizer_name', true);
    }
}

// Organizer Image (URL)
class CTT_Event_Organizer_Image_Tag extends \Elementor\Core\DynamicTags\Data_Tag {
    public function get_name() {
        return 'ctt-organizer-image';
    }
    
    public function get_title() {
        return 'Organizer Image';
    }
    
    public function get_group() {
        return 'ctt-fields';
    }
    
    public function get_categories() {
        return [\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY];
    }
    
    public function get_value(array $options = []) {
        $image_id = get_post_meta(get_the_ID(), '_ctt_organizer_img', true);
        
        if (!$image_id) {
            return [];
        }
        
        return [
            'id' => $image_id,
            'url' => wp_get_attachment_image_url($image_id, 'full'),
        ];
    }
}

// Event Categories
class CTT_Event_Categories_Tag extends CTT_Base_Tag {
    public function get_name() {
        return 'ctt-event-categories';
    }
    
    public function get_title() {
        return 'Event Categories';
    }
    
    public function render() {
        $categories = wp_get_post_terms(get_the_ID(), 'ctt_event_category', ['fields' => 'names']);
        if (!empty($categories) && !is_wp_error($categories)) {
            echo implode(', ', $categories);
        }
    }
}
