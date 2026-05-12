# Facebook Messenger Integration Setup Guide

This guide will help you set up Facebook Messenger integration for your Laravel application.

## Prerequisites

1. A Facebook Developer Account
2. A Facebook Page (Business or Personal)
3. Laravel application with authentication

## Step 1: Create a Facebook App

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Click "Create App" or "My Apps" > "Create App"
3. Choose "Business" as the app type
4. Fill in your app details:
   - App name
   - App contact email
   - Business account (if applicable)

## Step 2: Configure Messenger

1. In your Facebook App dashboard, go to "Add a Product"
2. Find "Messenger" and click "Set Up"
3. Generate a Page Access Token:
   - Go to Messenger > Settings
   - Under "Access Tokens", select your page
   - Generate and copy the token

## Step 3: Webhooks Setup

1. In Messenger > Settings, find "Webhooks"
2. Click "Add Callback URL"
3. Enter your webhook URL: `https://yourdomain.com/facebook/webhook`
4. Enter a Verify Token (any string you choose)
5. Subscribe to these events:
   - `messages`
   - `messaging_postbacks`
   - `messaging_optins`
   - `message_deliveries`
   - `message_reads`

## Step 4: Environment Configuration

Add these variables to your `.env` file:

```env
FACEBOOK_APP_ID=your_facebook_app_id
FACEBOOK_APP_SECRET=your_facebook_app_secret
FACEBOOK_WEBHOOK_VERIFY_TOKEN=your_chosen_verify_token
```

## Step 5: Database Setup

Run the migrations to create the necessary tables:

```bash
php artisan migrate
```

## Step 6: Facebook App Review (Production)

For production use, you'll need to submit your app for review:

1. Go to App Review > Permissions and Features
2. Request these permissions:
   - `pages_messaging`
   - `pages_show_list`
   - `pages_manage_metadata`

## How It Works

### User Flow

1. User clicks "Connect Facebook Account" in the app
2. Redirected to Facebook OAuth login
3. User grants permissions for pages
4. App stores user's Facebook account and pages
5. User can setup webhooks for each page
6. When messages are received, AI generates replies

### Webhook Handling

- Webhooks are received at `/facebook/webhook`
- Messages are stored in the database
- AI generates automatic replies
- Replies are sent back via Facebook API

## Customization

### AI Reply Logic

Edit the `generateAIReply()` method in `FacebookController.php` to implement your AI logic:

```php
protected function generateAIReply(string $messageText): string
{
    // Integrate with OpenAI, Claude, or your preferred AI service
    // Return the generated reply
}
```

### Permissions

The app requests these Facebook permissions:
- `email` - User's email
- `pages_show_list` - List user's pages
- `pages_messaging` - Send/receive messages
- `pages_manage_metadata` - Manage page settings
- `read_page_mailboxes` - Read page conversations

## Security Notes

- Store access tokens securely
- Use HTTPS for webhooks
- Validate webhook signatures in production
- Implement rate limiting

## Troubleshooting

### Common Issues

1. **Webhook verification fails**: Check your verify token matches
2. **Messages not received**: Ensure webhook is properly subscribed
3. **Permission errors**: Check app permissions and review status
4. **Token expired**: Implement token refresh logic

### Logs

Check Laravel logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

## API Reference

### Facebook Graph API
- [Messenger Platform](https://developers.facebook.com/docs/messenger-platform/)
- [Webhooks](https://developers.facebook.com/docs/messenger-platform/webhooks/)
- [Send API](https://developers.facebook.com/docs/messenger-platform/send-messages/)

### Laravel Integration
- Models: `FacebookAccount`, `FacebookPage`, `FacebookMessage`
- Controller: `FacebookController`
- Routes: `/facebook/*`

## Support

For issues with Facebook integration:
1. Check Facebook Developer Docs
2. Review app permissions and review status
3. Test with Facebook's webhook testing tools
4. Check Laravel logs for application errors
