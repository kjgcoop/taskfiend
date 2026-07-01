// From https://stackoverflow.com/questions/13154552/how-can-i-set-a-cookie-with-expire-time#13156752
function setCookie(name, value) {
    let now = new Date();
    let time = now.getTime();
    let expireTime = time + 400*24*60*1000; // 400 days - the max that Chrome will allow per https://developer.chrome.com/blog/cookie-max-age-expires/

    now.setTime(expireTime);
//    let string = name + '=' + value + ';expires='+now.toUTCString()+';path=/';
    document.cookie = name + '=' + value + ';expires='+now.toUTCString()+';path=/';
}

// https://www.w3schools.com/js/js_cookies.asp
function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for(let i = 0; i <ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

function showCookieConsent() {
//    alert('in function');
    console.debug('in function');

    // https://www.w3schools.com/howto/howto_js_popup.asp
    var banner = document.getElementById("cookie_banner");
    console.debug(banner);
    addBannerPadding();
    banner.style.display = "flex";
}

function addBannerPadding() {
    document.getElementById('cookie_banner').style.visibility = 'visible';
    document.body.style.paddingBottom = document.getElementById('cookie_banner').offsetHeight + 'px';
}

function removeBannerPadding() {
    document.getElementById('cookie_banner').style.visibility = 'hidden';
    document.body.style.paddingBottom = '0';
}

document.addEventListener("DOMContentLoaded", function (event) {
    let cookie_banner = document.getElementById('cookie_banner');
    let cookie_name = 'cookie_consent';
    let cookie_value = 'deny';

    if (getCookie(cookie_name) == cookie_value) {
        // If they've clicked the cookie banner previously, hide it now.
        cookie_banner.style.display = 'none';

        // Offer to let them revisit it.
//        document.getElementById('revisit_cookie_banner').style.display = 'list-item';
    } else {
        // If they haven't, add the padding at the bottom of the page so that
        // the banner doesn't cover the footer.
        addBannerPadding();
    }

    // Banner has been acknowledged.
    document.getElementById('cookie_banner').addEventListener("click", function (event) {
        removeBannerPadding();
//        document.getElementById('revisit_cookie_banner').style.display = 'list-item';
        document.getElementById('cookie_banner').style.display = 'none';
        setCookie(cookie_name, cookie_value);
    })


    // Set up the code to revisit the cookie banner - can do this even if the cookie is already in place.
    /*    document.getElementById("revisit_cookie_banner").addEventListener("click", function(event){
            console.debug('clicked');

            // Don't show the cookie banner if it's already showing.
            if (cookie_banner.style.display == 'none') {
                console.log("Show cookie banner");
                document.getElementById('cookie_banner').style.display = 'flex';

                // Hide the link to restore the cookie banner because it's already showing.
                document.getElementById('revisit_cookie_banner').style.display = 'none';

                showCookieConsent();
            } else {
                console.log("Not showing cookie banner because it's already there");
            }
        });*/


});
