package dz.saidi.staff.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import dz.saidi.staff.api.ApiClient
import dz.saidi.staff.api.DashboardResponse
import dz.saidi.staff.data.Session
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(onOpenOrder: (Long) -> Unit, onUnread: (Int) -> Unit, onLogout: () -> Unit) {
    var data by remember { mutableStateOf<DashboardResponse?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var loading by remember { mutableStateOf(true) }
    val scope = rememberCoroutineScope()

    fun load() {
        loading = true; error = null
        scope.launch {
            try {
                data = ApiClient.service.dashboard()
                onUnread(data!!.totals.unreadNotifications)
            } catch (e: Exception) {
                error = ApiClient.errorMessage(e)
            } finally { loading = false }
        }
    }
    LaunchedEffect(Unit) { load() }

    Scaffold(topBar = {
        TopAppBar(
            title = {
                Column {
                    Text("Bonjour, ${Session.userName.substringBefore(' ')} 👋", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    Text(Session.roleLabel, fontSize = 12.sp, color = MaterialTheme.colorScheme.primary)
                }
            },
            actions = {
                IconButton(onClick = { load() }) { Icon(Icons.Filled.Refresh, "Actualiser") }
                IconButton(onClick = onLogout) { Icon(Icons.AutoMirrored.Filled.Logout, "Déconnexion") }
            },
        )
    }) { pad ->
        when {
            loading && data == null -> Box(Modifier.fillMaxSize().padding(pad), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
            error != null && data == null -> ErrorBox(error!!, Modifier.padding(pad)) { load() }
            else -> data?.let { d ->
                LazyColumn(Modifier.fillMaxSize().padding(pad), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    item {
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            StatCard("Aujourd'hui", "${d.today.orders}", "commandes", Modifier.weight(1f))
                            StatCard("Recettes du jour", money(d.today.revenue), "confirmées+", Modifier.weight(1f))
                        }
                    }
                    item {
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            StatCard("En attente", "${d.totals.ordersPending}", "à traiter", Modifier.weight(1f), highlight = d.totals.ordersPending > 0)
                            StatCard("Stock bas", "${d.totals.lowStock}", "produits ≤ 3", Modifier.weight(1f), highlight = d.totals.lowStock > 0)
                        }
                    }
                    item { MiniChart(d.chart.map { it.orders }) }
                    item {
                        Text("Dernières commandes", fontWeight = FontWeight.Bold, fontSize = 16.sp, modifier = Modifier.padding(top = 4.dp))
                    }
                    items(d.recentOrders) { o ->
                        Card(Modifier.fillMaxWidth().clickable { onOpenOrder(o.id) }) {
                            Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(o.reference, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                    Text("${o.customerName} · ${o.wilaya ?: "—"}", fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(money(o.total), fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                    StatusChip(o.status, o.statusLabel)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun StatCard(title: String, value: String, sub: String, modifier: Modifier = Modifier, highlight: Boolean = false) {
    Card(
        modifier,
        colors = CardDefaults.cardColors(
            containerColor = if (highlight) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface
        ),
    ) {
        Column(Modifier.padding(14.dp)) {
            Text(title, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(value, fontSize = 22.sp, fontWeight = FontWeight.Black)
            Text(sub, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

/** 14-day order-count bars, no chart library needed. */
@Composable
fun MiniChart(values: List<Int>) {
    val max = (values.maxOrNull() ?: 0).coerceAtLeast(1)
    Card(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(14.dp)) {
            Text("Commandes — 14 derniers jours", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(8.dp))
            Row(Modifier.fillMaxWidth().height(64.dp), horizontalArrangement = Arrangement.spacedBy(3.dp), verticalAlignment = Alignment.Bottom) {
                values.forEach { v ->
                    Box(
                        Modifier
                            .weight(1f)
                            .fillMaxHeight(fraction = (v.toFloat() / max).coerceAtLeast(0.04f))
                            .clip(RoundedCornerShape(topStart = 3.dp, topEnd = 3.dp))
                            .background(if (v > 0) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surfaceVariant)
                    )
                }
            }
        }
    }
}

@Composable
fun StatusChip(status: String, label: String) {
    val c = statusColor(status)
    Text(
        label,
        fontSize = 11.sp,
        fontWeight = FontWeight.SemiBold,
        color = c,
        modifier = Modifier
            .clip(RoundedCornerShape(50))
            .background(c.copy(alpha = 0.14f))
            .padding(horizontal = 8.dp, vertical = 2.dp),
    )
}

@Composable
fun ErrorBox(message: String, modifier: Modifier = Modifier, onRetry: () -> Unit) {
    Column(modifier.fillMaxSize().padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
        Text("😕", fontSize = 40.sp)
        Spacer(Modifier.height(8.dp))
        Text(message, color = MaterialTheme.colorScheme.error, fontSize = 14.sp)
        Spacer(Modifier.height(16.dp))
        Button(onClick = onRetry) { Text("Réessayer") }
    }
}
