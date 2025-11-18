# Sell My Images - User Documentation

Sell My Images monetizes website images by adding "Download Hi-Res" buttons to content images. Users purchase AI-upscaled versions (4x, 8x) via secure Stripe payments and Upsampler.com processing.

## Core System Architecture

### Admin Interface
- [**Settings Page**](admin/settings-page.md) - Three-tab configuration interface for API settings, display control, and download management
- [**Analytics Page**](admin/analytics-page.md) - User engagement tracking and revenue performance metrics
- [**Jobs Management**](admin/jobs-page.md) - Complete processing request management with retry capabilities

### API Integration
- [**REST Endpoints**](api/rest-endpoints.md) - Public API for pricing, checkout, analytics, downloads, and status polling
- [**Cost Calculator**](api/cost-calculator.md) - Dynamic pricing system based on image dimensions and resolution multipliers  
- [**Stripe Integration**](api/stripe-integration.md) - Payment processing with checkout sessions, webhooks, and automatic refunds
- [**Upsampler Integration**](api/upsampler-integration.md) - AI-powered image upscaling through Precise Upscale endpoint

### Content Processing
- [**Block Processor**](content/block-processor.md) - Gutenberg image block detection and download button injection
- [**Featured Image Processor**](content/featured-image-processor.md) - Automatic button injection for featured images with conflict detection
- [**Filter Manager**](content/filter-manager.md) - Display control with flexible inclusion/exclusion rules

### Data Management
- [**Database Manager**](managers/database-manager.md) - Centralized CRUD operations with automated schema management
- [**Job Manager**](managers/job-manager.md) - Complete lifecycle management from creation to completion
- [**Download Manager**](managers/download-manager.md) - Secure file delivery with token-based authentication
- [**File Manager**](managers/file-manager.md) - Secure file storage and management for upscaled images
- [**Analytics Tracker**](managers/analytics-tracker.md) - User engagement tracking via WordPress post meta
- [**Webhook Manager**](managers/webhook-manager.md) - Shared webhook utilities and handler registration

### Service Layer
- [**Payment Service**](services/payment-service.md) - Complete payment workflow coordination with Stripe integration
- [**Upscaling Service**](services/upscaling-service.md) - AI image processing coordination and file management

### Frontend Interface
- [**Modal System**](frontend/modal-system.md) - User interface for pricing, payment, and status tracking
- [**Button Injection**](frontend/button-injection.md) - Automatic button placement with theme compatibility

### System Configuration
- [**Plugin Settings**](configuration/plugin-settings.md) - Comprehensive configuration options for all system aspects
- [**Webhook Endpoints**](configuration/webhook-endpoints.md) - Secure external service integration bypassing WordPress routing

### Database Schema
- [**Jobs Table**](database/jobs-table.md) - Complete workflow tracking with financial and processing data
- [**Analytics Storage**](database/analytics-storage.md) - WordPress post meta click tracking and engagement metrics

### Process Workflows
- [**Purchase Workflow**](workflows/purchase-workflow.md) - Complete customer journey from button click to download
- [**Upscaling Workflow**](workflows/upscaling-workflow.md) - AI-powered image processing using Upsampler API

### Communication System
- [**Email Notifications**](email/notification-system.md) - Professional HTML notifications for download delivery

## Key Features

**Monetization System**
- AI-powered image upscaling (4x, 8x resolution)
- Stripe-based secure payment processing
- Dynamic pricing based on image dimensions
- Automatic refund processing for failures

**User Experience**
- One-click download button integration
- Professional modal interface
- Real-time processing status updates
- Mobile-optimized responsive design

**Content Integration**
- Gutenberg block compatibility
- Theme-agnostic button injection
- Dynamic content support
- Performance-optimized asset loading

**Business Intelligence**
- Click tracking and conversion analytics
- Revenue reporting and profit analysis
- Customer engagement metrics
- Administrative oversight tools

**Security & Compliance**
- Token-based download security
- GDPR-compliant data handling
- Secure webhook processing
- SSL-encrypted payment flow

## System Requirements

**WordPress Environment**
- WordPress 5.0+ (Gutenberg required)
- PHP 7.4+
- SSL certificate (required for live payments)
- Writable uploads directory

**External Services**
- Stripe account (payment processing)
- Upsampler API key (AI image upscaling)
- Valid webhook endpoints

**Technical Dependencies**
- Composer autoloader
- jQuery (frontend interactions)
- WordPress REST API
- MySQL database with indexes

This documentation covers the complete technical implementation of the Sell My Images plugin for end-user understanding and system utilization.