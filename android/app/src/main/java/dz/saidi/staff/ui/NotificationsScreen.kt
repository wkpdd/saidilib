package dz.saidi.staff.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import dz.saidi.staff.api.ApiClient
import dz.saidi.staff.api.NotificationItem
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsScreen(onUnread: (Int) -> Unit) {
    var items by remember { mutableStateOf(listOf<NotificationItem>()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun load() {
        loading = true; error = null
        scope.launch {
            try {
                val res = ApiClient.service.notifications()
                items = res.notifications
                // Opening the feed marks everything read (same as web admin).
                if (res.unread > 0) {
                    ApiClient.service.markNotificationsRead()
                }
                onUnread(0)
            } catch (e: Exception) { error = ApiClient.errorMessage(e) }
            finally { loading = false }
        }
    }
    LaunchedEffect(Unit) { load() }

    Scaffold(topBar = { TopAppBar(title = { Text("Alertes", fontWeight = FontWeight.Bold) }) }) { pad ->
        when {
            loading && items.isEmpty() -> Box(Modifier.fillMaxSize().padding(pad), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            error != null && items.isEmpty() -> ErrorBox(error!!, Modifier.padding(pad)) { load() }
            items.isEmpty() -> Box(Modifier.fillMaxSize().padding(pad), contentAlignment = Alignment.Center) {
                Text("Aucune alerte 🎉", color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            else -> LazyColumn(Modifier.fillMaxSize().padding(pad), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(items) { n ->
                    Card(
                        colors = CardDefaults.cardColors(
                            containerColor = if (!n.read) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface
                        )
                    ) {
                        Row(Modifier.padding(12.dp)) {
                            Text(n.icon ?: "🔔", fontSize = 20.sp)
                            Spacer(Modifier.width(10.dp))
                            Column {
                                Text(n.title, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                n.body?.let { Text(it, fontSize = 13.sp) }
                                Text(n.at.take(16).replace('T', ' '), fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }
                }
            }
        }
    }
}
