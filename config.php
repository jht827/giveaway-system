<?php
# Configuration file for the giveaway system.
# Make changes in this file, not in the application code.
#
# Configuration settings are defined below.

# Protect against web entry
if (!defined('GIVEAWAY_SYSTEM')) {
    exit;
}

## Site branding
$gsSiteName = '旧一代无料分发登记系统';
$gsOwnerName = '杰瑞';
$gsSiteVersion = 'V1.0';
$gsAdminVersion = 'v1.0';

## Social media settings
$gsSocialPlatform = 'QQ';
$gsSocialIdNumericOnly = true;

## Tracking API
$gsTrackingApiKey = '';

## Database configuration
$gsDbHost = '127.0.0.1';
$gsDbName = 'giveaway_sys';
$gsDbUser = 'giveaway_sys';
$gsDbPass = 'urdbpassword';
$gsDbCharset = 'utf8mb4';
