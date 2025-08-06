# Session Implementation Guide

## Overview

This document outlines the session-based authentication implementation for the Symphonia backend, including fixes for the image corruption issue.

## Session Configuration

### Backend Changes Made

1. **Middleware Configuration** (`bootstrap/app.php`)
   - Added session middleware to both web and API routes
   - Ensures sessions work properly across all endpoints

2. **Authentication Controller** (`app/Http/Controllers/AuthController.php`)
   - Updated login method to work with sessions
   - Added session regeneration for security
   - Fixed profile picture upload to prevent corruption
   - Added image validation and cleanup methods

3. **API Routes** (`routes/api.php`)
   - Added session-based authentication routes
   - Added image upload test endpoint
   - Maintained existing Sanctum token authentication

## Session-Based Authentication Routes

### Login
```
POST /api/auth/session/login
Content-Type: multipart/form-data

Parameters:
- email: string
- password: string
- remember: boolean (optional)
```

### Logout
```
POST /api/auth/session/logout
```

### Check Authentication Status
```
GET /api/auth/session/check
```

### Get User Information
```
GET /api/auth/session/user
```

### Update Profile
```
POST /api/auth/session/user
Content-Type: multipart/form-data

Parameters:
- name: string (optional)
- email: string (optional)
- gender: string (optional)
- phone: string (optional)
- bio: string (optional)
- address: string (optional)
- dob: date (optional)
- profile_picture: file (optional)
```

## Image Upload Fixes

### Issues Fixed

1. **File Extension Preservation**
   - Now preserves original file extensions (jpg, png, gif)
   - No longer forces all images to .jpg format

2. **Enhanced Validation**
   - Validates file size (max 2MB)
   - Validates MIME types
   - Validates file extensions
   - Uses `getimagesize()` to verify actual image files

3. **Error Handling**
   - Comprehensive error logging
   - Automatic cleanup of corrupted files
   - Detailed error messages for debugging

4. **Corruption Prevention**
   - Validates uploaded files are actual images
   - Cleans up corrupted profile pictures automatically
   - Better file storage handling

### Image Upload Process

1. **Validation**
   - Check file validity
   - Verify file size (max 2MB)
   - Validate MIME type
   - Validate file extension

2. **Storage**
   - Generate unique filename with original extension
   - Store in `storage/app/public/images/profile_pictures/`
   - Verify file was stored correctly

3. **Post-Processing**
   - Validate stored file is actually an image
   - Clean up if validation fails
   - Update user profile with new image URL

## Frontend Implementation

### JavaScript Utility (`public/session-auth.js`)

The `SessionAuth` class provides easy-to-use methods for session management:

```javascript
// Initialize
const auth = new SessionAuth('http://localhost:8000/api');

// Login
const result = await auth.login('user@example.com', 'password', true);

// Check authentication
const status = await auth.checkAuth();

// Get user info
const user = await auth.getUser();

// Update profile
const updateResult = await auth.updateProfile({
    name: 'New Name',
    profile_picture: fileObject
});

// Logout
await auth.logout();
```

### Event Handlers

You can override event handlers for custom behavior:

```javascript
auth.onLoginSuccess = (data) => {
    console.log('User logged in:', data.user);
    // Redirect or update UI
};

auth.onLoginError = (error) => {
    console.error('Login failed:', error);
    // Show error message
};
```

## Testing

### Image Upload Test

Visit `http://localhost:8000/test-image-upload.html` to test image upload functionality.

### Session Test

Use the JavaScript utility to test session authentication:

```javascript
// Test session authentication
const auth = new SessionAuth();
const result = await auth.checkAuth();
console.log('Authenticated:', result.authenticated);
```

## Configuration

### Environment Variables

Ensure these are set in your `.env` file:

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

### CORS Configuration

The CORS configuration in `config/cors.php` includes:

```php
'supports_credentials' => true,
'allowed_origins' => ['http://localhost:5173', 'http://127.0.0.1:5173'],
```

## Security Considerations

1. **Session Security**
   - Sessions are regenerated on login
   - Sessions are invalidated on logout
   - CSRF protection is enabled

2. **File Upload Security**
   - File type validation
   - File size limits
   - Secure file storage
   - Automatic cleanup of corrupted files

3. **CORS Security**
   - Credentials support enabled
   - Specific origin restrictions
   - Proper headers configuration

## Troubleshooting

### Image Corruption Issues

1. **Check file upload settings**
   ```bash
   curl http://localhost:8000/api/debug-upload-settings
   ```

2. **Test image upload**
   - Use the test page at `/test-image-upload.html`
   - Check browser console for errors

3. **Check server logs**
   - Look for image processing errors
   - Verify file storage permissions

### Session Issues

1. **Check session configuration**
   - Verify database sessions table exists
   - Check session driver configuration

2. **Test session endpoints**
   ```bash
   curl -X POST http://localhost:8000/api/auth/session/login \
     -F "email=test@example.com" \
     -F "password=password" \
     -c cookies.txt
   ```

3. **Verify CORS settings**
   - Ensure `supports_credentials` is true
   - Check allowed origins

## Migration Notes

If you have existing corrupted images:

1. The system will automatically clean up corrupted profile pictures
2. Users can re-upload their profile pictures
3. Old corrupted files are automatically deleted

## API Response Examples

### Successful Login
```json
{
  "message": "Login successful",
  "access_token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "profile_picture": "/storage/images/profile_pictures/1_1234567890.jpg"
  },
  "session_id": "abc123def456",
  "redirect_url": "http://localhost:5173/"
}
```

### Authentication Check
```json
{
  "authenticated": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "profile_picture": "/storage/images/profile_pictures/1_1234567890.jpg",
    "role": "user",
    "is_email_verified": true
  },
  "session_id": "abc123def456"
}
```

This implementation provides a robust session-based authentication system with proper image upload handling and comprehensive error management. 