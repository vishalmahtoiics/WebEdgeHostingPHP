-- Fresh guide: passing Google Business Profile verification (video, phone, postcard).
INSERT INTO blog_posts (slug, title, excerpt, body, cover_image, cover_alt, category, service_id, meta_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
SELECT 'google-business-profile-verification-guide', 'Google Business Profile Verification: How to Pass It First Time', 'You created your business listing, but Google wants a video, the postcard never came or the verification keeps failing. Here is how verification works and how to record a video that gets approved.', 'Creating a listing on Google Maps takes five minutes. Getting it verified can take weeks. Many business owners in India get stuck at the same point: Google asks for a video, the video is rejected, the "verification pending" message never goes away, or the postcard never arrives. Until Google Business Profile verification is complete, your business may not appear properly on Maps, you cannot reply to reviews and you cannot edit key details. This guide explains how verification works, which method you will see, how to record a video Google accepts, and what to do if it fails.

## Why does Google verify businesses?

Google wants to be sure that the business is real, exists at the address shown (or serves the area it claims) and is managed by the person asking. Fake listings and scams on Maps are a big problem, so verification has become stricter. Once your profile is verified:

- It can show fully on Google Search and Maps.
- You can reply to reviews, post updates and add photos as the owner.
- You get insights on calls, direction requests and website clicks.

## Before you start: set the profile up correctly

Most verification failures come from mistakes made before verification even begins. Check these first:

- **Business name:** exactly as on your signboard and documents. No extra keywords like "Best Dentist in Pune".
- **Address:** a real place where you meet customers, with the same spelling as on your documents. No virtual office or PO box.
- **Service-area business:** if you visit customers (plumbers, tutors, pest control), hide the address and set service areas instead.
- **Category:** the most specific primary category, such as "Dental clinic".
- **Phone and website:** your real business number and website, showing the same name and address.

If a profile for your business already exists (maybe created by an old employee or agency), claim or request access to it instead of creating a duplicate – duplicates are a common reason for rejection or suspension.

## Which verification method will you get?

Google decides which methods to offer based on your category, location and history. You cannot choose freely, but you may see one or more of these:

| Method | How it works |
|---|---|
| Video recording | You record a short, continuous video of the business and upload it from the app |
| Live video call | You show the business to a Google support person on a scheduled video call |
| Phone or SMS | A code is sent to the business phone number |
| Email | A code is sent to a business email address |
| Postcard | A code is posted to the business address (less common now) |
| Instant | Sometimes offered if the website is already verified in Google Search Console |

For most new businesses in India, **video verification** is the method Google asks for.

## How to record a verification video that gets approved

Google reviews the video to confirm three things: **location**, **business** and **management**. Plan it before you hit record:

1. **Use one continuous take.** No cuts, edits, filters or music. One to two minutes is usually enough.
2. **Start outside.** Show the street, a nearby landmark or shop, and the building, so Google can match the location to the map pin.
3. **Show permanent signage.** Your business name on a board, door or wall – printed and fixed, not a paper sheet held for the video.
4. **Go inside and show the business working.** The counter, products, equipment, work area or treatment room – whatever proves this is the business in your profile.
5. **Show proof that you manage it.** Unlock the door or shutter with your key, open the cash counter or billing software, or show staff-only areas.
6. **Record in daylight with a steady hand,** from the Google Maps or Google Business Profile app on your phone.

Upload it from the same Google account that manages the profile. Reviews usually take a few working days.

**Service-area businesses** without a shop should show their vehicle with branding, tools and equipment, and documents or materials with the business name, instead of a storefront.

## What if verification fails or stays "pending"?

- **Read the message** in the profile. Google often says if it could not confirm the location or the business.
- **Fix the cause:** add permanent signage, correct the address or map pin, or switch to a service-area business if you do not receive customers.
- **Try again when the option appears.** Record a clearer video that covers location, business and management.
- **Do not create a new profile** to get around the problem – that can lead to suspension.
- **Contact support** through the Business Profile Help Center if verification stays pending for a long time.

If your profile was already verified and is now suspended, that is a different process – see our guide on [Google Business Profile suspension](/blog/google-business-profile-suspended-fix).

## After verification: make the profile work for you

A verified profile is only the start. To appear higher on Google Maps:

- Add real photos of the outside, inside, team and work every month.
- Ask every happy customer for a review and reply to all reviews.
- Post offers and updates regularly.
- Keep hours, holidays and contact details accurate.

Our [local SEO guide](/blog/local-seo-get-found-on-google) explains how these signals help you rank, and our guide on [removing fake Google reviews](/blog/remove-fake-google-reviews) helps you protect your rating.

## Frequently asked questions

### How long does Google Business Profile verification take?

Video verifications are usually reviewed within a few working days. Phone, SMS and email codes are instant. Postcards can take a couple of weeks to arrive. Some reviews take longer when Google needs to check more details.

### Why was my Google Business Profile verification video rejected?

Usually because the video did not clearly show the location, permanent signage, the business in operation or proof that you manage it, or because it was edited or cut. Record one continuous video in daylight covering all of these and try again.

### Can I verify a Google Business Profile without a shop?

Yes. Service-area businesses that visit customers can verify by showing branded vehicles, tools, equipment and business documents instead of a storefront, with the address hidden and service areas set.

### Can I choose the verification method?

Not directly. Google decides which methods to offer based on your business type, location and history. You may see more options later, or after correcting your business details.

### Should I create a new profile if verification keeps failing?

No. Creating duplicate profiles can lead to suspension. Fix the reason for the failure, such as signage, address or category, and request verification again on the same profile.

## Summary

Google Business Profile verification is much easier when your profile matches reality: the right name, a real address or service area, the correct category and visible signage. Record one continuous video that shows the location, the business working and proof that you manage it, upload it from the owner''s account, and avoid creating duplicate profiles. Once verified, keep adding photos, reviews and updates to rank higher on Maps.

Want us to set up and verify your profile? Our [SEO and local SEO services](/services/seo) include Google Business Profile setup, verification support and ongoing management. [Contact us](/contact) and we will help you get found on Google Maps.', 'assets/blog/google-business-profile-verification-guide.jpg', 'Google Business Profile Verification: How to Pass It First Time – SEO guide by WebEdge Solution', 'SEO', (SELECT id FROM site_services WHERE slug = 'seo' LIMIT 1), 'Google Business Profile Verification: Video Guide (India)', 'Stuck on Google Business Profile verification? Learn which method to choose, how to record a video Google accepts, and what to do if verification fails.', 'google business profile verification', 'published', NOW(), NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM blog_posts WHERE slug = 'google-business-profile-verification-guide');
