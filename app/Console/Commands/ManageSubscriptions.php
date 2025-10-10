<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ManageSubscriptions extends Command
{
    protected $signature = 'subscription:manage {action} {user_id?}';
    protected $description = 'Manage user subscriptions';

    public function handle()
    {
        $action = $this->argument('action');
        $userId = $this->argument('user_id');

        switch ($action) {
            case 'list':
                $this->listSubscriptions();
                break;
            case 'cancel':
                if ($userId) {
                    $this->cancelUserSubscription($userId);
                } else {
                    $this->error('User ID required for cancel action');
                }
                break;
            case 'sync':
                $this->syncSubscriptions();
                break;
            default:
                $this->error('Invalid action. Available: list, cancel, sync');
        }
    }

    private function listSubscriptions()
    {
        $users = User::whereHas('subscriptions')->with('subscriptions')->get();

        $this->table(
            ['User ID', 'Email', 'Subscription Status', 'Tier', 'Ends At'],
            $users->map(function ($user) {
                $subscription = $user->subscription('default');
                return [
                    $user->id,
                    $user->email,
                    $subscription?->stripe_status ?? 'None',
                    $user->getSubscriptionTier(),
                    $subscription?->ends_at ?? 'N/A',
                ];
            })
        );
    }

    private function cancelUserSubscription($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            $this->error('User not found');
            return;
        }

        if (!$user->isSubscribed()) {
            $this->error('User is not subscribed');
            return;
        }

        $user->subscription('default')->cancel();
        $this->info("Subscription cancelled for user {$user->email}");
    }

    private function syncSubscriptions()
    {
        $users = User::whereNotNull('stripe_id')->get();

        foreach ($users as $user) {
            try {
                $user->syncSubscriptions();
                $this->info("Synced subscriptions for {$user->email}");
            } catch (\Exception $e) {
                $this->error("Failed to sync for {$user->email}: {$e->getMessage()}");
            }
        }
    }
}
