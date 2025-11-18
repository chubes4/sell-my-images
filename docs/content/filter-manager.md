# Filter Manager

The Filter Manager controls where download buttons appear across your WordPress site with flexible inclusion and exclusion rules.

## Display Control Modes

**All Posts (Default)**
Shows buttons on all posts and pages:
- Zero performance overhead
- Immediate `true` return
- No database queries
- Universal coverage

**Exclude Selected**
Hide buttons on specific content:
- Post type exclusions
- Category/tag filtering
- Individual post targeting
- Conditional logic support

**Include Only Selected**
Show buttons only on specified content:
- Whitelist approach
- Precise targeting
- Performance optimization
- Selective monetization

## Filter Criteria

**Post Type Filtering**
- Built-in types: posts, pages, custom post types
- Multiple selection support
- Hierarchical post type handling
- Archive page considerations

**Taxonomy Filtering**
- Category inclusion/exclusion
- Tag-based filtering
- Custom taxonomy support
- Term relationship checking

**Individual Post Control**
- Specific post ID targeting
- Bulk selection interface
- Search and filter tools
- Quick edit integration

## Performance Optimization

**Efficient Checking**
```php
public static function should_show_buttons( $post_id = null ) {
    $mode = get_option( 'smi_display_mode', 'all' );
    
    // Fast path for default mode
    if ( $mode === 'all' ) {
        return true;
    }
    
    // Context-aware filtering
    return self::evaluate_display_rules( $post_id, $mode );
}
```

**Caching Strategy**
- Per-request result caching
- Object cache integration
- Transient storage for complex rules
- Automatic cache invalidation

## Configuration Storage

**Option Structure**
- `smi_display_mode` - Control mode (all/exclude/include)
- `smi_filter_post_types` - Post type filtering array
- `smi_filter_categories` - Category filtering array
- `smi_filter_tags` - Tag filtering array
- `smi_filter_post_ids` - Specific post IDs (comma-separated)

## Integration Points

**Content Processing**
- Block processor integration
- Asset loading optimization
- Template compatibility
- Widget area support

**Admin Interface**
- Settings page configuration
- Quick edit controls
- Bulk operations
- Preview functionality

## Advanced Filtering

**Custom Rules**
Support for complex filtering logic:
- Multiple criteria combination (AND/OR)
- Date-based filtering
- User role restrictions
- Custom field conditions

**Hook System**
```php
// Custom filtering logic
add_filter( 'smi_should_show_buttons', function( $show, $post_id ) {
    // Custom logic here
    return $show;
}, 10, 2 );
```

**Conditional Display**
- Logged-in user restrictions
- Geographic targeting
- Device-based filtering
- Time-based controls