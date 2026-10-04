{{--
    Cookie consent for analytics (operator GA / GTM / Meta Pixel, platform GA).
    Tracking scripts are printed inert (type="text/plain" data-consent="analytics") and only
    switched on after the visitor taps Accept. Decline means no tracking script ever loads.
    The choice is kept for 180 days in the te_consent cookie. Include once per page, only
    when the page has a tracker. The banner is built by script, so this works inside <head>.
--}}
@once
<script>
    (function () {
        var NAME = 'te_consent';
        var MAX_AGE = 60 * 60 * 24 * 180;

        function read() {
            var match = document.cookie.match(/(?:^|; )te_consent=([^;]*)/);
            return match ? match[1] : null;
        }

        function write(value) {
            document.cookie = NAME + '=' + value + '; Max-Age=' + MAX_AGE + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        }

        function activate() {
            document.querySelectorAll('script[type="text/plain"][data-consent="analytics"]').forEach(function (inert) {
                var live = document.createElement('script');
                if (inert.dataset.src) {
                    live.src = inert.dataset.src;
                    live.async = true;
                } else {
                    live.text = inert.text;
                }
                inert.parentNode.insertBefore(live, inert.nextSibling);
                inert.removeAttribute('data-consent');
            });
        }

        function removeBanner() {
            var banner = document.getElementById('te-consent-banner');
            if (banner) banner.remove();
        }

        function showBanner() {
            if (document.getElementById('te-consent-banner')) return;
            var banner = document.createElement('div');
            banner.id = 'te-consent-banner';
            banner.setAttribute('role', 'dialog');
            banner.setAttribute('aria-label', @js(__('Cookie choice')));
            banner.style.cssText = 'position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;max-width:580px;margin:0 auto;background:rgba(15,23,42,0.96);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);color:#f8fafc;border-radius:20px;border:1px solid rgba(255,255,255,0.15);padding:14px 18px;font:13px/1.5 system-ui,-apple-system,BlinkMacSystemFont,sans-serif;box-shadow:0 20px 40px -10px rgba(0,0,0,0.5),0 0 0 1px rgba(255,255,255,0.05);display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;box-sizing:border-box';
            banner.innerHTML = '<span style="flex:1 1 260px;font-size:13px;line-height:1.5;color:#e2e8f0;font-weight:500"></span><span style="display:flex;gap:10px;align-items:center;flex-shrink:0"><button type="button" data-choice="denied" style="padding:9px 18px;border-radius:12px;border:1.5px solid rgba(255,255,255,0.3);background:rgba(255,255,255,0.08);color:#ffffff;font-size:13px;font-weight:700;line-height:1.2;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all 0.15s ease;box-sizing:border-box"></button><button type="button" data-choice="granted" style="padding:9px 18px;border-radius:12px;border:1.5px solid #ffffff;background:#ffffff;color:#0f172a;font-size:13px;font-weight:700;line-height:1.2;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all 0.15s ease;box-sizing:border-box"></button></span>';
            banner.querySelector('span').textContent = @js(__('We use cookies to understand how visitors use this site. Is that OK?'));
            banner.querySelector('[data-choice="denied"]').textContent = @js(__('Decline'));
            banner.querySelector('[data-choice="granted"]').textContent = @js(__('Accept'));
            banner.addEventListener('click', function (event) {
                var choice = event.target.getAttribute && event.target.getAttribute('data-choice');
                if (!choice) return;
                write(choice);
                removeBanner();
                if (choice === 'granted') activate();
            });
            document.body.appendChild(banner);
        }

        window.teConsent = {
            reset: function () {
                document.cookie = NAME + '=; Max-Age=0; Path=/';
                showBanner();
            }
        };

        function start() {
            var choice = read();
            if (choice === 'granted') activate();
            else if (choice !== 'denied') showBanner();
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
        else start();
    })();
</script>
@endonce
