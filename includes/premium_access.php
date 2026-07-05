<?php

declare(strict_types=1);

if (!function_exists('has_premium_access')) {
    /**
     * Check premium access by validating the Stripe subscription status.
     *
     * @param array|object $user User payload/record containing subscription identifiers.
     */
    function has_premium_access($user): bool
    {
        $subscriptionId = '';

        if (is_array($user)) {
            $subscriptionId = (string)($user['subscription_id'] ?? $user['stripe_subscription_id'] ?? '');
        } elseif (is_object($user)) {
            $subscriptionId = (string)($user->subscription_id ?? $user->stripe_subscription_id ?? '');
        }

        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return false;
        }

        $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            return false;
        }

        require_once $autoloadPath;

        $secretKey = getenv('STRIPE_SECRET_KEY');
        if (!$secretKey && isset($_ENV['STRIPE_SECRET_KEY'])) {
            $secretKey = (string)$_ENV['STRIPE_SECRET_KEY'];
        }
        if (!$secretKey && isset($_SERVER['STRIPE_SECRET_KEY'])) {
            $secretKey = (string)$_SERVER['STRIPE_SECRET_KEY'];
        }

        if (!$secretKey) {
            return false;
        }

        $stripeClass = '\\Stripe\\Stripe';
        $subscriptionClass = '\\Stripe\\Subscription';

        try {
            $stripeClass::setApiKey($secretKey);
            $subscription = $subscriptionClass::retrieve($subscriptionId);
            $status = strtolower((string)($subscription->status ?? ''));

            return $status === 'active';
        } catch (Throwable $e) {
            error_log('has_premium_access error: ' . $e->getMessage());
            return false;
        }
    }
}
