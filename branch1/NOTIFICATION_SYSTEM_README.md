# Notification System Integration

This notification system integrates with **ntfy** (or can be self-hosted) to provide push notifications for inventory alerts and chat messages.

## Features

✅ **Bell Icon Notification Dropdown**
- Click the bell icon in the header to view all notifications
- Badge shows unread count (red dot or number)
- Tabs: All, Alerts, Messages

✅ **Inventory Alerts**
- Low Stock alerts
- Out of Stock alerts
- Expiring Soon alerts (within 30 days)
- Expired items alerts

✅ **Chat Message Notifications**
- Unread message count
- New message notifications from other users

✅ **ntfy Integration**
- Optional push notifications via ntfy.sh or self-hosted instance
- Configurable per user or global

## Database Setup

Run the SQL in `pharmaceutical_db.sql` to create the `Notifications` table:

```sql
CREATE TABLE IF NOT EXISTS Notifications (
    NotificationID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    Type ENUM('alert', 'chat', 'info', 'warning', 'error') DEFAULT 'info',
    Title VARCHAR(255) NOT NULL,
    Message TEXT,
    Link VARCHAR(500),
    IsRead TINYINT(1) DEFAULT 0,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    ReadAt DATETIME NULL,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID) ON DELETE CASCADE,
    INDEX idx_user_unread (UserID, IsRead),
    INDEX idx_created (CreatedAt)
);
```

## ntfy Configuration (Optional)

### Option 1: Use Public ntfy.sh (Free)

1. No configuration needed - works out of the box
2. Users can subscribe to their topic using the ntfy mobile app
3. Topic format: `pharma-notifications-{USERID}`

### Option 2: Self-Hosted ntfy

1. Install ntfy server (see: https://github.com/binwiederhier/ntfy)
2. Set environment variables or create config file:
   ```bash
   export NTFY_ENABLED=true
   export NTFY_SERVER=http://your-ntfy-server.com
   export NTFY_TOPIC=pharma-alerts
   ```

3. Or create `branch1/config/ntfy_enabled.txt` to enable

### Option 3: Disable ntfy (Database Only)

- Leave `NTFY_ENABLED` unset or false
- Notifications will still work in the web interface
- No push notifications will be sent

## Files Created

1. **`branch1/api/notification_api.php`** - Backend API for notifications
   - `get_notifications` - Fetch all notifications
   - `mark_read` - Mark notification as read
   - `mark_all_read` - Mark all as read
   - `send_notification` - Send new notification (with ntfy)

2. **`branch1/includes/notification_dropdown.php`** - UI component
   - Dropdown menu with tabs
   - Notification list with styling
   - Badge/count display

3. **`branch1/js/notifications.js`** - Frontend JavaScript
   - Fetches notifications every 30 seconds
   - Displays badge count
   - Handles click events
   - Integrates with alerts and chat

4. **`pharmaceutical_db.sql`** - Database schema
   - Added `Notifications` table

## Usage

### For Users

1. **View Notifications**: Click the bell icon in the header
2. **Filter**: Use tabs (All, Alerts, Messages)
3. **Mark as Read**: Click "Mark all read" or click individual notifications
4. **Navigate**: Click a notification to go to the related page

### For Developers

#### Send a Notification Programmatically

```php
// In your PHP code
$data = [
    'user_id' => $user_id,
    'type' => 'alert', // 'alert', 'chat', 'info', 'warning', 'error'
    'title' => 'Low Stock Alert',
    'message' => 'Medicine X is running low',
    'link' => 'med_inventory.php'
];

$ch = curl_init('api/notification_api.php?action=send_notification');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
```

#### Send via JavaScript

```javascript
fetch('api/notification_api.php?action=send_notification', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        user_id: userId,
        type: 'alert',
        title: 'New Alert',
        message: 'Alert message',
        link: 'med_inventory.php'
    })
});
```

## Notification Types

- **`alert`** - General alerts (yellow badge)
- **`chat`** - Chat messages (blue badge)
- **`info`** - Information (blue badge)
- **`warning`** - Warnings (orange badge)
- **`error`** - Errors (red badge)

## Auto-Integration

The system automatically:
- ✅ Fetches inventory alerts from `medicine_api.php`
- ✅ Fetches chat notifications from `chat_api.php`
- ✅ Updates badge count in real-time
- ✅ Polls for new notifications every 30 seconds
- ✅ Sends push notifications via ntfy (if enabled)

## Mobile App Setup (ntfy)

1. Install ntfy app on your phone:
   - Android: [Google Play](https://play.google.com/store/apps/details?id=io.heckel.ntfy) or [F-Droid](https://f-droid.org/packages/io.heckel.ntfy/)
   - iOS: [App Store](https://apps.apple.com/app/ntfy/id1625396347)

2. Subscribe to your topic:
   - Topic: `pharma-notifications-{YOUR_USER_ID}`
   - Or use the global topic if configured

3. Receive push notifications on your phone!

## Troubleshooting

### Notifications not showing?
- Check browser console for errors
- Verify `Notifications` table exists in database
- Check API endpoint: `api/notification_api.php?action=get_notifications`

### Badge not updating?
- Clear browser cache
- Check JavaScript console for errors
- Verify `notifications.js` is loaded

### ntfy not working?
- Check `NTFY_ENABLED` environment variable
- Verify `NTFY_SERVER` URL is correct
- Check server logs for curl errors
- Test ntfy server manually: `curl -d "test" https://ntfy.sh/your-topic`

## Security Notes

- Notifications are user-specific (filtered by `UserID`)
- Only authenticated users can access notifications
- ntfy topics should be user-specific for privacy
- Consider using authentication for self-hosted ntfy

## Future Enhancements

- [ ] Email notifications
- [ ] SMS notifications
- [ ] Notification preferences per user
- [ ] Sound alerts
- [ ] Desktop notifications (browser API)
- [ ] Notification history page

