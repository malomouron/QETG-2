(() => {
    'use strict';

    const iconPath = document.currentScript?.dataset.iconSrc || '';
    if (!window.tarteaucitron) {
        return;
    }

    window.tarteaucitron.init({
        privacyUrl: '',
        hashtag: '#tarteaucitron',
        cookieName: 'tarteaucitron',
        orientation: 'bottom',
        groupServices: false,
        showAlertSmall: true,
        cookieslist: true,
        closePopup: false,
        showIcon: true,
        iconSrc: iconPath,
        iconPosition: 'BottomLeft',
        adblocker: true,
        DenyAllCta: false,
        AcceptAllCta: true,
        highPrivacy: false,
        handleBrowserDNTRequest: false,
        removeCredit: false,
        moreInfoLink: true,
        useExternalCss: false,
        useExternalJs: false,
        readmoreLink: '',
        mandatory: false,
    });
    window.tarteaucitron.user.gajsUa = 'UA-159054530-1';
    window.tarteaucitron.user.gajsMore = function () {};
    const jobs = (window.tarteaucitron.job = window.tarteaucitron.job || []);
    jobs.push('gajs');
    if (document.querySelector('.g-recaptcha[data-sitekey]')) {
        jobs.push('recaptcha');
    }
})();
