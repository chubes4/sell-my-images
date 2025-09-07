# Modal System

The modal system provides the user interface for image upscaling with pricing, payment, and status tracking.

## Modal Structure

**HTML Template**
Modal structure defined in `templates/modal.php`:
- Header with title and close button
- Loading state with spinner
- Error message display area
- Main content with image preview and options
- Footer with action buttons

**CSS Framework**
Maximum z-index (2147483647) prevents overlay issues:
```css
.smi-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 2147483647; /* Prevents ad overlays */
}
```

## Interactive Features

**Resolution Selection**
Two quality options with dynamic pricing:
- Standard Quality (4x): Perfect for prints and web
- Premium Quality (8x): Professional print quality

**Price Calculation**
Real-time pricing via REST API:
```javascript
// Load pricing for all resolutions
$.ajax({
    url: wpApiSettings.root + 'smi/v1/calculate-all-prices',
    data: {
        attachment_id: attachmentId,
        post_id: postId
    }
});
```

## Payment Flow

**Checkout Process**
1. User selects resolution and enters email
2. JavaScript creates checkout session via API
3. Redirect to Stripe-hosted payment page
4. Return to site with payment status
5. Modal displays processing status

**Status Polling**
Real-time job status updates:
```javascript
// Poll every 10 seconds for up to 10 minutes
setInterval(() => {
    $.get(wpApiSettings.root + 'smi/v1/job-status/' + jobId)
        .done(response => updateJobStatus(response));
}, 10000);
```

## State Management

**Modal States**
- Loading: Initial data retrieval
- Main: Resolution selection and email input
- Processing: Payment and upscaling status
- Completed: Download ready notification
- Error: Problem resolution interface

**State Transitions**
```javascript
// State management methods
showLoading(true/false)
showMainContent(true/false)
showError(message)
showPaymentSuccess(jobId)
showJobCompleted(jobData)
```

## Dynamic Content Support

**Button Injection**
Automatic button detection and injection:
```javascript
// Multiple detection methods for theme compatibility
var attachmentMatch = imgClasses.match(/wp-image-(\d+)/);
if (!attachmentMatch) {
    // Try picture element
    attachmentMatch = pictureClasses.match(/wp-image-(\d+)/);
}
```

**Real-time Updates**
Support for dynamic content loading:
- MutationObserver for new content
- Event system for manual triggers
- Theme compatibility layer
- Performance optimization

## User Experience

**Accessibility**
- Keyboard navigation support (ESC to close)
- Screen reader compatible
- Focus management
- ARIA labels and descriptions

**Mobile Optimization**
- Touch-friendly interface
- Responsive design
- Ad overlay prevention
- Viewport handling

## Error Handling

**User-Friendly Messages**
- Configuration errors: "Service temporarily unavailable"
- Payment failures: "Payment could not be processed"
- Processing errors: "Image processing failed"
- Network issues: "Connection problem detected"

**Recovery Options**
- Retry buttons for transient errors
- Alternative payment methods
- Support contact information
- Manual intervention escalation

## Integration Points

**WordPress Integration**
- Post ID detection from body classes
- WordPress REST API authentication
- Admin AJAX for authenticated requests
- Localization support

**External Services**
- Stripe checkout session creation
- Upsampler status monitoring
- Email notification triggers
- Analytics tracking