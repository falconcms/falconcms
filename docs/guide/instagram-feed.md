# Instagram Feed <Badge type="warning" text="Pro" />

The **Instagram Feed** builder element shows your latest Instagram posts as a grid or a
swipeable row, with your profile picture, follower count and a Follow button. It is an
advanced builder element, part of [FalconCMS Pro](/guide/pro).

## Connecting your account

Instagram only lets websites read **Business** and **Creator** accounts.

1. Switch the account to Business or Creator in the Instagram app: **Settings → Account type**
   ([Instagram's help](https://help.instagram.com/502981923235522)).
2. At [developers.facebook.com/apps](https://developers.facebook.com/apps), create an app of type
   *Business* and add the **Instagram** product, choosing *API setup with Instagram login*
   ([Meta's guide](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/get-started)).
3. Under *Generate access tokens*, add your Instagram account (accept the tester invitation in
   the Instagram app if asked) and click **Generate token**.
4. In FalconCMS open **Appearance → Customizer → Instagram**, paste the token (it starts with
   `IG`) and press **Save Changes**.

The section then shows the connected account, how many posts are saved and when they were last
checked. The token is stored encrypted and renewed every week, so it does not lapse after 60 days.

**Fetch latest posts** gets new posts at once; **Disconnect Instagram** removes the token and the
saved posts and pictures.

## How it stays fast

Pages never wait for Instagram. The posts are saved on your site and checked for new ones in the
background, every hour by default (**Check for new posts every** in the Customizer). The pictures
are saved on your site too (**Save pictures on this site**), so they keep working when Instagram's
own picture links expire, and readers' browsers never contact Meta. If Instagram is down or the
token stops working, the last saved posts keep showing and the Customizer says what went wrong.

On a server with a scheduler, `falcon:instagram-sync` runs hourly; without one, a page that shows
the feed fetches new posts after its response has been sent.

## Adding the element

In the builder, add **Instagram Feed**. The canvas shows your real posts.

**General:** where the pictures come from (the connected account, or pictures you choose), number
of posts, grid or carousel, columns per device, picture shape (square, portrait, landscape),
spacing, profile header, caption (on hover, below, or hidden), hover effect and how links open.

**Design:** corner radius, overlay and icon colours, caption and username typography, button style
and margins.

### A personal account?

Instagram stopped letting websites read personal accounts in December 2024. Switching to a free
**Creator** account takes a minute and keeps your followers and posts. Or, without a token:

- **Instagram post links (any public account).** In Instagram, open a post or reel and choose
  **Share → Copy link**, then add the link under **Post links**. Each post is shown with
  Instagram's own embedded card (picture or video, likes, caption if **Caption** is not hidden).
  The account must be public, new posts are not added by themselves, and Instagram's script
  loads on the page. On the builder canvas each post is a stand-in card; the real one appears on
  the page.
- **Pictures I choose.** Add the pictures and links yourself, in the element's own design.

For both, type your username for the profile header and the Follow button.
