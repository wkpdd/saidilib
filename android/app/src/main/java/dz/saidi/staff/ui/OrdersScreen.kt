package dz.saidi.staff.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import dz.saidi.staff.api.ApiClient
import dz.saidi.staff.api.OrderBrief
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class, FlowPreview::class)
@Composable
fun OrdersScreen(onOpen: (Long) -> Unit, onNew: () -> Unit) {
    var orders by remember { mutableStateOf(listOf<OrderBrief>()) }
    var counts by remember { mutableStateOf(mapOf<String, Int>()) }
    var filter by remember { mutableStateOf<String?>(null) }
    var search by remember { mutableStateOf("") }
    var page by remember { mutableStateOf(1) }
    var hasMore by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun load(reset: Boolean = true) {
        if (reset) { page = 1 }
        loading = true; error = null
        scope.launch {
            try {
                val res = ApiClient.service.orders(filter, search.ifBlank { null }, page)
                orders = if (reset) res.orders else orders + res.orders
                counts = res.statusCounts
                hasMore = res.hasMore
            } catch (e: Exception) { error = ApiClient.errorMessage(e) }
            finally { loading = false }
        }
    }

    LaunchedEffect(filter) { load() }
    LaunchedEffect(Unit) {
        snapshotFlow { search }.debounce(400).collect { load() }
    }

    Scaffold(
        topBar = { TopAppBar(title = { Text("Commandes", fontWeight = FontWeight.Bold) }) },
        floatingActionButton = {
            ExtendedFloatingActionButton(onClick = onNew) { Text("+ Commande") }
        },
    ) { pad ->
        Column(Modifier.fillMaxSize().padding(pad)) {
            OutlinedTextField(
                value = search, onValueChange = { search = it },
                placeholder = { Text("Référence, client, téléphone…") },
                leadingIcon = { Icon(Icons.Filled.Search, null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
            )

            LazyRow(contentPadding = PaddingValues(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                item {
                    FilterChip(selected = filter == null, onClick = { filter = null },
                        label = { Text("Toutes (${counts.values.sum()})") })
                }
                items(STATUS_LABELS.keys.toList()) { s ->
                    FilterChip(
                        selected = filter == s,
                        onClick = { filter = if (filter == s) null else s },
                        label = { Text("${STATUS_LABELS[s]} (${counts[s] ?: 0})") },
                    )
                }
            }

            when {
                loading && orders.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                error != null && orders.isEmpty() -> ErrorBox(error!!) { load() }
                orders.isEmpty() -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("Aucune commande", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                else -> LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(orders) { o ->
                        Card(Modifier.fillMaxWidth().clickable { onOpen(o.id) }) {
                            Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(o.reference, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                    Text(o.customerName, fontSize = 13.sp)
                                    Text("${o.wilaya ?: "—"} · ${o.phone ?: ""}", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(money(o.total), fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                    StatusChip(o.status, o.statusLabel)
                                }
                            }
                        }
                    }
                    if (hasMore) {
                        item {
                            OutlinedButton(
                                onClick = { page += 1; load(reset = false) },
                                modifier = Modifier.fillMaxWidth(),
                                enabled = !loading,
                            ) { Text(if (loading) "Chargement…" else "Charger plus") }
                        }
                    }
                }
            }
        }
    }
}
