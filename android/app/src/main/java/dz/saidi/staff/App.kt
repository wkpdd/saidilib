package dz.saidi.staff

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import dz.saidi.staff.data.Session

class App : Application() {
    override fun onCreate() {
        super.onCreate()
        Session.init(this)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val nm = getSystemService(NotificationManager::class.java)
            nm.createNotificationChannel(
                NotificationChannel("orders", "Commandes", NotificationManager.IMPORTANCE_HIGH)
                    .apply { description = "Nouvelles commandes et alertes" }
            )
        }
    }
}
