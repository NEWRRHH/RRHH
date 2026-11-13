// POST /api/broadcasting/auth — private channel authorization for
// Laravel Echo / Reverb. Pusher's client posts the socket_id + channel_name
// here; the Bearer token is injected from the httpOnly cookie so the browser
// never needs access to it.
export default defineEventHandler(async (event) => {
  try {
    const { status, upstream, body } = await proxyFetch(event, `${apiBase()}/broadcasting/auth`)
    replayResponse(event, status, upstream)
    return body
  } catch {
    return upstreamUnavailable(event)
  }
})
