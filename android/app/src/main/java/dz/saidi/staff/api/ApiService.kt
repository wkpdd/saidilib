package dz.saidi.staff.api

import retrofit2.http.*

interface ApiService {
    @POST("api/v1/login")
    suspend fun login(@Body body: LoginRequest): LoginResponse

    @POST("api/v1/logout")
    suspend fun logout()

    @POST("api/v1/fcm-token")
    suspend fun registerFcm(@Body body: FcmTokenRequest)

    @GET("api/v1/dashboard")
    suspend fun dashboard(): DashboardResponse

    @GET("api/v1/orders")
    suspend fun orders(
        @Query("status") status: String? = null,
        @Query("q") search: String? = null,
        @Query("page") page: Int = 1,
    ): OrdersResponse

    @GET("api/v1/orders/{id}")
    suspend fun order(@Path("id") id: Long): OrderResponse

    @PATCH("api/v1/orders/{id}/status")
    suspend fun updateOrderStatus(@Path("id") id: Long, @Body body: StatusRequest): OrderResponse

    @POST("api/v1/orders/{id}/prices")
    suspend fun editOrderPrices(@Path("id") id: Long, @Body body: PriceEditRequest): OrderResponse

    @POST("api/v1/orders/{id}/dispatch")
    suspend fun dispatchOrder(@Path("id") id: Long, @Body body: DispatchRequest): OrderResponse

    @POST("api/v1/orders/{id}/refund")
    suspend fun refundOrder(@Path("id") id: Long, @Body body: RefundRequest): OrderResponse

    @GET("api/v1/products")
    suspend fun products(
        @Query("q") search: String? = null,
        @Query("low_stock") lowStock: Boolean? = null,
        @Query("page") page: Int = 1,
    ): ProductsResponse

    @GET("api/v1/products/lookup")
    suspend fun lookup(@Query("sku") sku: String): LookupResponse

    @PATCH("api/v1/products/{id}")
    suspend fun quickUpdateProduct(@Path("id") id: Long, @Body body: QuickUpdateRequest): ProductResponse

    @GET("api/v1/clients")
    suspend fun clients(
        @Query("q") search: String? = null,
        @Query("page") page: Int = 1,
    ): ClientsResponse

    @GET("api/v1/clients/{id}")
    suspend fun client(@Path("id") id: Long): ClientResponse

    @POST("api/v1/clients/{id}/transactions")
    suspend fun addTransaction(@Path("id") id: Long, @Body body: TransactionRequest): ClientBriefResponse

    @GET("api/v1/notifications")
    suspend fun notifications(@Query("page") page: Int = 1): NotificationsResponse

    @POST("api/v1/notifications/read")
    suspend fun markNotificationsRead()
}
