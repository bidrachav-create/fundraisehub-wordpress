# FundRaise-Hub WordPress API Integration Guide (v1)

This document explains how a WordPress plugin should connect to FundRaise-Hub's WordPress API, including URL patterns, request/response contracts, supported payloads, and security expectations.

## 1) Base URL and endpoint pattern

Your plugin must treat the base URL as configurable because it depends on where FundRaise-Hub is deployed.

- **Base URL (configurable):** `https://<fundraise-hub-domain>`
- **WordPress API root:** `<BASE_URL>/api/wp/v1`
- **Embed HTML endpoint:** `<BASE_URL>/embed/campaign/<campaignId>`

Examples:

- `https://app.fundraisehub.example/api/wp/v1/campaigns`
- `https://fundraise.myorg.org/api/wp/v1/design-system`
- `https://app.fundraisehub.example/embed/campaign/8b8c...`

## 2) API version and contract headers

Current contract version is `v1`.

- WordPress API responses include: `X-FRH-WP-Contract-Version: v1`
- Embed responses include: `X-FRH-WP-Embed-Contract-Version: v1`

Your plugin should read/log these headers for diagnostics and future version handling.

## 3) Authentication model

### 3.1 Bearer API key

All `/api/wp/v1/*` routes require:

```http
Authorization: Bearer <wordpress_connection_api_key>
```

No cookie/session auth is used for this API.

### 3.2 How keys are generated/stored

- Key format: 64 hex chars (32 random bytes).
- Server stores only:
  - `apiKeyHash` (SHA-256 hash)
  - `apiKeyPrefix` (first 8 chars, for display/audit)
- Plaintext key is returned only at creation/rotation time.

### 3.3 Public/private key or request-signature requirements

- **There is no public/private keypair requirement currently.**
- **There is no HMAC request signing requirement currently.**
- Security is based on HTTPS + Bearer key + origin controls + rate limiting.

## 4) Security and transport expectations

1. **HTTPS required in production** (never use plain HTTP in production plugin settings).
2. **Do not log full API keys** in WordPress logs/debug output.
3. **Store API key securely** in plugin settings and mask it in UI.
4. **Rate limit exists:** ~120 requests/minute per key prefix (best-effort per Worker isolate).
5. **Origin allowlist:** if configured on the FundRaise-Hub connection, browser-origin calls are checked against normalized origins.
6. **CORS preflight (`OPTIONS /api/wp/*`)** succeeds only for approved origins.

## 5) Common response envelope

### Success

```json
{ "success": true, "data": <payload> }
```

### Error

```json
{ "success": false, "error": "<message>" }
```

## 6) Core endpoints your plugin can call

## 6.1 `GET /api/wp/v1/campaigns`

Returns published/active campaigns in the connection scope.

### Response `data` type

`CampaignListItem[]`

```ts
type CampaignListItem = {
  id: string;
  name: string;
  slug: string;
  campaignType: string; // e.g. standard | raffle | dedication | chinese_auction
  status: string;       // published | active
  startDate: string | null;
  endDate: string | null;
  goalAmount: string;   // numeric string, default "0"
  raisedAmount: string; // numeric string, default "0"
  donorCount: number;   // default 0
  currency: string;     // default "USD"
  layout: string | null;
  colorPrimary: string | null;
  colorSecondary: string | null;
  colorAccent: string | null;
  bannerUrl: string | null;
  featuredImageUrl: string | null;
  description: string | null;
};
```

## 6.2 `GET /api/wp/v1/campaigns/:campaignId`

Returns full campaign page payload for a single campaign.

### Response `data` type

```ts
type CampaignDetailResponse = {
  campaign: CampaignListItem;
  teams: Team[];
  ambassadors: AmbassadorWithUserName[];
  comments: CampaignComment[];
  media: CampaignMedia[];
  recentDonations: PublicDonation[];
  paymentConfig: PaymentConfig;
  rafflePackages: RafflePackage[];
  dedicationCategories: DedicationCategoryWithItems[];
  auctionPrizes: AuctionPrize[];
  auctionTiers: AuctionTier[];
  auctionBundles: (AuctionBundle & { allocations: AuctionBundleAllocation[] })[];
};
```

#### Teams

```ts
type Team = {
  id: string;
  campaignId: string;
  name: string;
  slug: string;
  captainId: string | null;
  goalAmount: string;
  raisedAmount: string;
  avatarUrl: string | null;
  description: string | null;
  isActive: boolean;
  createdAt: string;
};
```

#### Ambassadors

```ts
type AmbassadorWithUserName = {
  id: string;
  campaignId: string;
  teamId: string | null;
  userId: string | null;
  displayName: string | null;
  email: string | null;
  pageSlug: string;
  goalAmount: string;
  raisedAmount: string;
  avatarUrl: string | null;
  personalMessage: string | null;
  personalVideoUrl: string | null;
  isMatching: boolean;
  isActive: boolean;
  createdAt: string;
  userName: string; // enriched by API
};
```

#### Comments

```ts
type CampaignComment = {
  id: string;
  campaignId: string;
  donationId: string | null;
  donorName: string | null;
  message: string;
  amount: string | null;
  isApproved: boolean;
  isPublic: boolean;
  createdAt: string;
  updatedAt: string;
};
```

#### Media

```ts
type CampaignMedia = {
  id: string;
  campaignId: string;
  url: string;
  mediaType: "image" | "video";
  caption: string | null;
  sortOrder: number;
  createdAt: string;
  updatedAt: string;
};
```

#### Recent donations (privacy-safe)

```ts
type PublicDonation = {
  id: string;
  amount: string;
  currency: string;
  donorName: string | null; // anonymized when needed
  isAnonymous: boolean;
  isOffline: boolean;
  honorOf: string | null;
  comment: string | null;
  createdAt: string;
  teamId: string | null;
  ambassadorId: string | null;
  recurringPlanId: string | null;
  totalPledgeAmount: string | null;
};
```

> The API intentionally strips donor PII/internal fields (for example donor email, donor ID, processor, status, hide flags).

#### Payment config

`paymentConfig` is processor-dependent. Common keys:

```ts
type PaymentConfig = {
  gatewayType: "stripe" | "sola" | "banquest" | "ojc" | null;
  hasGateway: boolean;
  tdfEnabled?: boolean;
  matbiaEnabled?: boolean;
  stripe?: { publishableKey: string; stripeAccountId?: string };
  sola?: { ifieldsKey: string; merchantId: string };
  banquest?: {
    connectionId: string;
    merchantId: string;
    tokenizationKey: string;
    isSandbox: boolean;
  };
  ojc?: { connectionId: string; ojcOrgId: string; isSandbox: boolean };
};
```

#### Dedication categories

```ts
type DedicationCategoryWithItems = {
  id: string;
  campaignId: string;
  name: string;
  description: string | null;
  imageUrl: string | null;
  pricePerUnit: string;
  totalQuantity: number;
  claimedQuantity: number;
  sortOrder: number;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
  items: {
    id: string;
    categoryId: string;
    itemLabel: string | null;
    status: "available" | "reserved" | "claimed";
    sortOrder: number;
    donationId: string | null;
    dedicationMessage: string | null;
    dedicationType: string | null;
    dedicatedBy: string | null;
    createdAt: string;
    updatedAt: string;
  }[];
};
```

#### Auction data

`auctionPrizes`, `auctionTiers`, and `auctionBundles` are returned for Chinese auction campaigns.

- `auctionTiers` includes tier definitions (name, pricePerChance, sortOrder, etc.)
- `auctionBundles` includes bundle info plus `allocations[]` (`tierId`, `chances`, etc.)
- `auctionPrizes` includes campaign prize rows (including `auctionTierId` when assigned)

## 6.3 `GET /api/wp/v1/design-system`

Returns organization branding/design settings.

### Response `data` type

```ts
type DesignSystemResponse = {
  name: string;
  slug: string;
  logoUrl: string | null;
  primaryColor: string | null;
  designSystem: {
    colors: Array<{ name: string; value: string }>;
    fonts: Array<{ name: string; family: string }>;
    headingPresets: Array<{
      name: string;
      fontSize: string;
      fontWeight: string;
      lineHeight: string;
    }>;
    buttonPresets: Array<{
      name: string;
      backgroundColor: string;
      textColor: string;
      borderRadius: string;
      padding: string;
      border?: string;
    }>;
    spacingScale: number[];
  };
};
```

If org-specific design data is missing, API returns a default design system.

## 6.4 `POST /api/wp/v1/feedback`

Creates a WordPress-originated feedback submission.

### Request body

```ts
type FeedbackRequest = {
  subject: string; // 1..150
  message: string; // 1..5000
  site: string;    // absolute http/https URL, max 255 chars
};
```

### Response

- `201 Created`
- Body: `{ "success": true }`

## 6.5 `POST /api/wp/v1/bug-reports`

Creates a WordPress-originated bug report submission.

### Request body

```ts
type BugReportRequest = {
  title: string;       // 1..150
  description: string; // 1..5000
  steps?: string;      // max 5000, default ""
  site: string;        // absolute http/https URL, max 255 chars
  wordpress?: string;  // max 50
  plugin?: string;     // max 50
  php?: string;        // max 50
};
```

### Response

- `201 Created`
- Body: `{ "success": true }`

## 7) Embed endpoint contract (for iframe/embed flows)

Endpoint:

- `GET /embed/campaign/:campaignId`
- `OPTIONS /embed/campaign/:campaignId`

Behavior:

- Origin allowlist checks are performed against active WordPress connection entries.
- If a valid `Origin` header is present, the server uses it and ignores `Referer` parsing.
- `Referer` is only used as a fallback when `Origin` is missing or `Origin: null` (opaque origin).
- Malformed headers are rejected with `403` in the active parsing path (malformed `Origin`, or fallback malformed `Referer`).
- Success includes `X-FRH-WP-Embed-Contract-Version: v1`.

The embed HTML posts these messages to the parent frame:

- `FRH_RESIZE` with `{ height, campaignId }`
- `FRH_OPEN_MODAL` with `{ modal: "donation", campaignId }`
- `FRH_DONATION_COMPLETE` with `{ campaignId }`

Messaging requirement notes:

- `FRH_*` messages are posted only when the embed can derive a valid `targetOrigin` from `document.referrer`.
- If `document.referrer` is empty or unparseable (for example due to strict referrer policies/privacy tooling), no `postMessage` events are emitted.
- Messages are also only emitted when the page is actually running inside an iframe (`window.parent !== window`).
- Parent page should send `FRH_INIT` after iframe load to trigger first resize sync when messaging is available.

## 8) Connection provisioning and key lifecycle (admin-side)

These are typically used by FundRaise-Hub admin UI (not public WordPress pages), but are relevant for plugin setup workflows.

Base path:

- `/api/organizations/:orgId/wordpress-connections`

Supported operations:

- `GET /api/organizations/:orgId/wordpress-connections`
- `POST /api/organizations/:orgId/wordpress-connections`
- `PUT /api/organizations/:orgId/wordpress-connections/:connectionId`
- `POST /api/organizations/:orgId/wordpress-connections/:connectionId/rotate`
- `DELETE /api/organizations/:orgId/wordpress-connections/:connectionId` (soft-deactivate)

`POST` create body:

```ts
type CreateConnection = {
  label: string; // 1..255
  scopeType?: "org" | "program"; // default org
  programId?: string; // required for program scope
  allowedOrigins?: string[]; // URL list; empty = unrestricted
};
```

Create and rotate responses return plaintext `apiKey` once; store immediately.

## 9) Status codes to handle in the plugin

- `200` success for GET
- `201` success for feedback/bug creation
- `204` preflight success
- `400` validation errors (bad payload)
- `401` missing/invalid auth or origin/key mismatch (intentionally ambiguous)
- `403` origin not allowed on embed/preflight
- `404` campaign/org/connection not found
- `429` rate-limited
- `500` unexpected server error

## 10) WordPress plugin implementation checklist

1. Add settings for:
   - FundRaise-Hub base URL
   - API key
   - optional timeout/retry settings
2. Build endpoint URLs from base URL (no hardcoded domain).
3. Send `Authorization: Bearer ...` on all `/api/wp/v1/*` calls.
4. Parse `{ success, data/error }` envelope and surface helpful admin errors.
5. Treat monetary fields as **strings**; do not float-calculate with JS/PHP floats without decimal-safe handling.
6. Cache reads (`campaigns`, `campaign details`, `design-system`) with short TTL to reduce rate-limit pressure.
7. Add a key rotation UX path (update plugin config after key rotation).
8. Never expose API key in front-end JS, page source, or logs.

## 11) Backward/forward compatibility guidance

- Assume `v1` may add fields over time.
- Ignore unknown fields safely.
- Keep required-field validation strict only for fields your plugin truly needs.
- Log contract version header + endpoint + response status for supportability.
