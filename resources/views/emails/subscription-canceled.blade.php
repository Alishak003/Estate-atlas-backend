@component('mail::message')
# 👋 Hello {{ $firstName }} {{ $lastName }},

Your **{{ $planName }}** subscription on **{{ config('app.name') }}** has now **ended**.

We’re grateful for the time you’ve spent with us and hope you’ve enjoyed using our platform.  
Your account is still accessible, but premium features are no longer active.

---

### Here’s what happens next
- You **won’t be billed** going forward.  
- Your data and settings remain safe in your account.  
- You can **reactivate your subscription anytime** with just one click.

If you have any questions or feedback, we’d love to hear from you — simply reply to this email or reach out through our support page.

Thanks again for being part of **{{ config('app.name') }}** 💙  
We truly hope to see you again soon!

Warm regards,  
**The {{ config('app.name') }} Team**

---

*This email confirms that your subscription ended on {{ $endedAt->format('F j, Y') }}.*
@endcomponent
