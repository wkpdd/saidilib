package dz.saidi.staff

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.navigation.compose.*
import androidx.navigation.NavGraph.Companion.findStartDestination
import dz.saidi.staff.data.Session
import dz.saidi.staff.ui.*

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { SaidiTheme { Root() } }
    }
}

private data class Tab(val route: String, val label: String, val icon: androidx.compose.ui.graphics.vector.ImageVector, val perm: String?)

@Composable
fun Root() {
    var loggedIn by remember { mutableStateOf(Session.loggedIn) }

    if (!loggedIn) {
        LoginScreen(onSuccess = { loggedIn = true })
        return
    }

    val nav = rememberNavController()
    val tabs = remember {
        listOf(
            Tab("dashboard", "Accueil", Icons.Filled.Home, null),
            Tab("orders", "Commandes", Icons.Filled.Receipt, "orders"),
            Tab("products", "Produits", Icons.Filled.Inventory2, "products"),
            Tab("clients", "Clients", Icons.Filled.People, "clients"),
            Tab("notifications", "Alertes", Icons.Filled.Notifications, null),
        ).filter { it.perm == null || Session.can(it.perm) }
    }
    var unread by remember { mutableStateOf(0) }

    Scaffold(
        bottomBar = {
            NavigationBar {
                val current = nav.currentBackStackEntryAsState().value?.destination?.route
                tabs.forEach { tab ->
                    NavigationBarItem(
                        selected = current?.startsWith(tab.route) == true,
                        onClick = {
                            nav.navigate(tab.route) {
                                popUpTo(nav.graph.findStartDestination().id) { saveState = true }
                                launchSingleTop = true
                                restoreState = true
                            }
                        },
                        icon = {
                            if (tab.route == "notifications" && unread > 0) {
                                BadgedBox(badge = { Badge { Text("$unread") } }) { Icon(tab.icon, tab.label) }
                            } else Icon(tab.icon, tab.label)
                        },
                        label = { Text(tab.label) },
                    )
                }
            }
        }
    ) { pad ->
        NavHost(nav, startDestination = "dashboard", Modifier.padding(pad)) {
            composable("dashboard") {
                DashboardScreen(
                    onOpenOrder = { nav.navigate("order/$it") },
                    onUnread = { unread = it },
                    onLogout = { Session.clear(); loggedIn = false },
                )
            }
            composable("orders") {
                OrdersScreen(onOpen = { nav.navigate("order/$it") }, onNew = { nav.navigate("orderNew") })
            }
            composable("order/{id}") { entry ->
                OrderDetailScreen(entry.arguments?.getString("id")!!.toLong(), onBack = { nav.popBackStack() })
            }
            composable("orderNew") {
                NewOrderScreen(
                    onDone = { id -> nav.navigate("order/$id") { popUpTo("orders") } },
                    onBack = { nav.popBackStack() },
                )
            }
            composable("products") {
                ProductsScreen(
                    onOpen = { nav.navigate("product/$it") },
                    onCreate = { nav.navigate("product/0") },
                    onReceive = { nav.navigate("receive") },
                )
            }
            composable("product/{id}") { entry ->
                ProductDetailScreen(entry.arguments?.getString("id")!!.toLong(), onBack = { nav.popBackStack() })
            }
            composable("receive") { ReceiveStockScreen(onBack = { nav.popBackStack() }) }
            composable("clients") { ClientsScreen(onOpen = { nav.navigate("client/$it") }) }
            composable("client/{id}") { entry ->
                ClientDetailScreen(entry.arguments?.getString("id")!!.toLong(), onBack = { nav.popBackStack() })
            }
            composable("notifications") { NotificationsScreen(onUnread = { unread = it }) }
        }
    }
}
