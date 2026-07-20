package dz.saidi.papetrie;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.view.KeyEvent;
import android.webkit.CookieManager;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

/**
 * Native shell around https://saidi.h47.io — the whole store (and admin)
 * runs inside a full-screen WebView. Sessions/cookies persist, photo
 * uploads work from camera/gallery, downloads (PDF price list, slips)
 * open in the system browser, and tel:/WhatsApp links open their apps.
 */
public class MainActivity extends Activity {

    private static final String HOME_URL = "https://saidi.h47.io";
    private static final String HOST = "saidi.h47.io";
    private static final int FILE_CHOOSER_REQUEST = 1001;

    private WebView webView;
    private ValueCallback<Uri[]> pendingFileCallback;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        webView = new WebView(this);
        setContentView(webView);

        WebSettings s = webView.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        // Cache pages for weak-connection revisits (Algeria 3G/4G).
        s.setCacheMode(WebSettings.LOAD_DEFAULT);

        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, false);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri url = request.getUrl();
                String scheme = url.getScheme() == null ? "" : url.getScheme();
                // Keep the store itself inside the app…
                if (("https".equals(scheme) || "http".equals(scheme)) && HOST.equals(url.getHost())) {
                    return false;
                }
                // …everything else (tel:, WhatsApp, Facebook, other sites)
                // goes to the matching app or browser.
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, url));
                } catch (ActivityNotFoundException e) {
                    Toast.makeText(MainActivity.this, "Aucune application pour ouvrir ce lien", Toast.LENGTH_SHORT).show();
                }
                return true;
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    view.loadDataWithBaseURL(HOME_URL, OFFLINE_HTML, "text/html", "utf-8", null);
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback,
                                             FileChooserParams params) {
                // Product photo uploads from the admin: gallery or camera.
                if (pendingFileCallback != null) {
                    pendingFileCallback.onReceiveValue(null);
                }
                pendingFileCallback = callback;
                try {
                    startActivityForResult(params.createIntent(), FILE_CHOOSER_REQUEST);
                } catch (ActivityNotFoundException e) {
                    pendingFileCallback = null;
                    Toast.makeText(MainActivity.this, "Impossible d'ouvrir le sélecteur de fichiers", Toast.LENGTH_SHORT).show();
                    return false;
                }
                return true;
            }
        });

        // Downloads (PDF tarifs, bordereaux) → system browser handles them.
        webView.setDownloadListener((url, userAgent, contentDisposition, mimetype, contentLength) -> {
            try {
                startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
            } catch (ActivityNotFoundException ignored) {
            }
        });

        if (savedInstanceState == null) {
            webView.loadUrl(HOME_URL);
        } else {
            webView.restoreState(savedInstanceState);
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILE_CHOOSER_REQUEST && pendingFileCallback != null) {
            pendingFileCallback.onReceiveValue(
                WebChromeClient.FileChooserParams.parseResult(resultCode, data));
            pendingFileCallback = null;
            return;
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        // Hardware/gesture back walks the browsing history before exiting.
        if (keyCode == KeyEvent.KEYCODE_BACK && webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        webView.saveState(outState);
    }

    /** Branded offline page with a retry button (no connection at all). */
    private static final String OFFLINE_HTML =
        "<!doctype html><html lang='fr'><head><meta charset='utf-8'>" +
        "<meta name='viewport' content='width=device-width,initial-scale=1'>" +
        "<style>body{font-family:sans-serif;background:#FFF7ED;display:flex;align-items:center;" +
        "justify-content:center;min-height:100vh;margin:0;text-align:center;color:#431407}" +
        ".c{padding:2rem}h1{font-size:1.4rem}p{color:#9a3412}" +
        "button{background:#B85F00;color:#fff;border:0;border-radius:12px;padding:.9rem 2rem;" +
        "font-size:1rem;font-weight:700}</style></head><body><div class='c'>" +
        "<div style='font-size:3rem'>📡</div><h1>Pas de connexion</h1>" +
        "<p>Vérifiez votre connexion internet puis réessayez.</p>" +
        "<button onclick=\"location.href='" + HOME_URL + "'\">Réessayer</button>" +
        "</div></body></html>";
}
