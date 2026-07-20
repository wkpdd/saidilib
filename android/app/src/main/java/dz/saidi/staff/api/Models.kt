package dz.saidi.staff.api

import com.google.gson.annotations.SerializedName

data class LoginRequest(val email: String, val password: String, val device: String?)
data class LoginResponse(val token: String, val user: UserProfile)
data class UserProfile(
    val id: Long, val name: String, val email: String, val role: String,
    @SerializedName("role_label") val roleLabel: String,
    val permissions: List<String>,
)

data class DashboardResponse(
    val today: TodayStats,
    val totals: Totals,
    @SerializedName("status_counts") val statusCounts: Map<String, Int>,
    val chart: List<ChartDay>,
    @SerializedName("recent_orders") val recentOrders: List<OrderBrief>,
)
data class TodayStats(val orders: Int, val revenue: Double)
data class Totals(
    @SerializedName("orders_pending") val ordersPending: Int,
    @SerializedName("orders_total") val ordersTotal: Int,
    val revenue: Double,
    val products: Int,
    @SerializedName("low_stock") val lowStock: Int,
    @SerializedName("unread_notifications") val unreadNotifications: Int,
)
data class ChartDay(val date: String, val orders: Int)

data class OrderBrief(
    val id: Long, val reference: String,
    @SerializedName("customer_name") val customerName: String,
    val phone: String?, val wilaya: String?, val total: Double,
    val status: String, @SerializedName("status_label") val statusLabel: String,
    @SerializedName("created_at") val createdAt: String,
)

data class OrdersResponse(
    val orders: List<OrderBrief>,
    @SerializedName("has_more") val hasMore: Boolean,
    val page: Int,
    @SerializedName("status_counts") val statusCounts: Map<String, Int>,
)

data class OrderFull(
    val id: Long, val reference: String,
    @SerializedName("customer_name") val customerName: String,
    val phone: String?, val wilaya: String?, val total: Double,
    val status: String, @SerializedName("status_label") val statusLabel: String,
    @SerializedName("created_at") val createdAt: String,
    val commune: String?, val address: String?,
    @SerializedName("delivery_type") val deliveryType: String?,
    val notes: String?, val subtotal: Double,
    @SerializedName("delivery_fee") val deliveryFee: Double,
    val discount: Double,
    @SerializedName("is_editable") val isEditable: Boolean,
    val tracking: String?, val provider: String?,
    @SerializedName("dispatched_at") val dispatchedAt: String?,
    val refund: RefundInfo?,
    val client: ClientRef?,
    val items: List<OrderItem>,
    val adjustments: List<Adjustment>,
)
data class RefundInfo(val amount: Double, val method: String?, val reason: String?, val at: String)
data class ClientRef(val id: Long, val name: String, val type: String?)
data class OrderItem(
    val id: Long, val name: String, val variant: String?,
    val quantity: Int, @SerializedName("unit_price") val unitPrice: Double,
    @SerializedName("line_total") val lineTotal: Double,
)
data class Adjustment(
    val label: String?, @SerializedName("old_price") val oldPrice: Double,
    @SerializedName("new_price") val newPrice: Double,
    val reason: String?, val author: String?, val at: String,
)
data class OrderResponse(val ok: Boolean?, val order: OrderFull, val message: String?, val changed: Int?)

data class ProductBrief(
    val id: Long, val name: String,
    @SerializedName("display_name") val displayName: String,
    val sku: String?, val brand: String?, val category: String?,
    val price: Double, val stock: Int,
    @SerializedName("track_stock") val trackStock: Boolean,
    @SerializedName("is_active") val isActive: Boolean,
    val image: String?,
)
data class ProductsResponse(
    val products: List<ProductBrief>,
    @SerializedName("has_more") val hasMore: Boolean, val page: Int,
)
data class ProductResponse(val ok: Boolean?, val product: ProductBrief)
data class LookupResponse(val found: Boolean, val product: ProductBrief?)
data class QuickUpdateRequest(
    val price: Double? = null,
    @SerializedName("wholesale_price") val wholesalePrice: Double? = null,
    val stock: Int? = null,
    @SerializedName("is_active") val isActive: Boolean? = null,
)

data class ClientBrief(
    val id: Long, val name: String, val phone: String?, val type: String?,
    val balance: Double, @SerializedName("is_overdue") val isOverdue: Boolean,
    @SerializedName("orders_count") val ordersCount: Int,
    @SerializedName("is_active") val isActive: Boolean,
)
data class ClientsResponse(
    val clients: List<ClientBrief>,
    @SerializedName("has_more") val hasMore: Boolean, val page: Int,
)
data class ClientFull(
    val id: Long, val name: String, val phone: String?, val type: String?,
    val balance: Double, @SerializedName("is_overdue") val isOverdue: Boolean,
    @SerializedName("orders_count") val ordersCount: Int,
    @SerializedName("is_active") val isActive: Boolean,
    val email: String?, val commune: String?, val address: String?, val notes: String?,
    @SerializedName("credit_limit") val creditLimit: Double,
    val transactions: List<Transaction>,
)
data class ClientResponse(val ok: Boolean?, val client: ClientFull)
data class ClientBriefResponse(val ok: Boolean?, val client: ClientBrief)
data class Transaction(
    val id: Long, val type: String, val amount: Double,
    val description: String?, @SerializedName("order_ref") val orderRef: String?,
    val author: String?, val at: String,
)
data class TransactionRequest(val type: String, val amount: Double, val description: String?)

data class NotificationsResponse(
    val notifications: List<NotificationItem>,
    val unread: Int, @SerializedName("has_more") val hasMore: Boolean, val page: Int,
)
data class NotificationItem(
    val id: Long, val type: String?, val icon: String?, val title: String,
    val body: String?, val read: Boolean, val at: String,
)

data class StatusRequest(val status: String)
data class DispatchRequest(val provider: String, val tracking: String?)
data class RefundRequest(val amount: Double, val method: String, val reason: String?)
data class PriceEditRequest(val items: Map<String, PriceItem>, val reason: String?)
data class PriceItem(@SerializedName("unit_price") val unitPrice: Double)
data class FcmTokenRequest(@SerializedName("fcm_token") val fcmToken: String)
data class ApiMessage(val message: String?)
