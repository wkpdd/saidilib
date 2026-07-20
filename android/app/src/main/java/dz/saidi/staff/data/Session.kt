package dz.saidi.staff.data

import android.content.Context
import android.content.SharedPreferences

/** Persisted login state: server URL, bearer token, profile, permissions. */
object Session {
    private const val PREFS = "saidi_session"
    private lateinit var prefs: SharedPreferences

    fun init(context: Context) {
        prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    }

    var baseUrl: String
        get() = prefs.getString("base_url", "https://saidi.h47.io") ?: "https://saidi.h47.io"
        set(v) = prefs.edit().putString("base_url", v.trimEnd('/')).apply()

    var token: String?
        get() = prefs.getString("token", null)
        set(v) = prefs.edit().putString("token", v).apply()

    var userName: String
        get() = prefs.getString("user_name", "") ?: ""
        set(v) = prefs.edit().putString("user_name", v).apply()

    var roleLabel: String
        get() = prefs.getString("role_label", "") ?: ""
        set(v) = prefs.edit().putString("role_label", v).apply()

    var permissions: Set<String>
        get() = prefs.getStringSet("permissions", emptySet()) ?: emptySet()
        set(v) = prefs.edit().putStringSet("permissions", v).apply()

    fun can(perm: String) = permissions.contains(perm)

    val loggedIn get() = token != null

    fun clear() = prefs.edit().remove("token").remove("user_name")
        .remove("role_label").remove("permissions").apply()
}
