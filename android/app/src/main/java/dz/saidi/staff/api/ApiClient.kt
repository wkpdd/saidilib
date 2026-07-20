package dz.saidi.staff.api

import com.google.gson.Gson
import dz.saidi.staff.data.Session
import okhttp3.OkHttpClient
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object ApiClient {
    @Volatile private var cached: ApiService? = null
    @Volatile private var cachedBase: String? = null

    /** Rebuilt automatically whenever the configured server URL changes. */
    val service: ApiService
        get() {
            val base = Session.baseUrl
            return cached.takeIf { cachedBase == base } ?: build(base).also {
                cached = it; cachedBase = base
            }
        }

    private fun build(base: String): ApiService {
        val http = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                val req = chain.request().newBuilder()
                    .header("Accept", "application/json")
                    .apply { Session.token?.let { header("Authorization", "Bearer $it") } }
                    .build()
                chain.proceed(req)
            }
            .build()

        return Retrofit.Builder()
            .baseUrl("$base/")
            .client(http)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }

    /** Human-readable French message out of any API/network failure. */
    fun errorMessage(e: Throwable): String = when (e) {
        is HttpException -> {
            val body = e.response()?.errorBody()?.string()
            val parsed = try { Gson().fromJson(body, ApiMessage::class.java)?.message } catch (_: Exception) { null }
            parsed ?: when (e.code()) {
                401 -> "Session expirée — reconnectez-vous."
                403 -> "Vous n'avez pas la permission pour cette action."
                422 -> "Données invalides."
                else -> "Erreur serveur (${e.code()})."
            }
        }
        is java.net.UnknownHostException, is java.net.ConnectException ->
            "Connexion impossible — vérifiez internet et l'adresse du serveur."
        is java.net.SocketTimeoutException -> "Le serveur ne répond pas (délai dépassé)."
        else -> e.message ?: "Erreur inconnue."
    }
}
