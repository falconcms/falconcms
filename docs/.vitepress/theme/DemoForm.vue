<template>
  <div class="demo-form-wrapper">

    <div v-if="submitted" class="success-box">
      <div class="success-icon">✓</div>
      <h3>Check your inbox!</h3>
      <p>Demo access details have been sent to <strong>{{ submittedEmail }}</strong>.</p>
      <div class="cred-box">
        <p><strong>Demo URL:</strong> <a href="https://demo.falconcms.com" target="_blank">demo.falconcms.com</a></p>
        <p><strong>Email:</strong> admin@falconcms.com</p>
        <p><strong>Password:</strong> password</p>
      </div>
      <button class="btn-reset" @click="reset">Request another access →</button>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="demo-form">
      <div class="field-row">
        <div class="field">
          <label>Full Name <span class="req">*</span></label>
          <input v-model="form.name" type="text" placeholder="Your name" required />
        </div>
        <div class="field">
          <label>Work Email <span class="req">*</span></label>
          <input v-model="form.email" type="email" placeholder="you@company.com" required />
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Website / Company <span class="optional">(optional)</span></label>
          <input v-model="form.website" type="text" placeholder="yoursite.com" />
        </div>
        <div class="field">
          <label>How did you find us? <span class="optional">(optional)</span></label>
          <select v-model="form.source">
            <option value="">Select...</option>
            <option>Google</option>
            <option>GitHub</option>
            <option>Packagist</option>
            <option>Social Media</option>
            <option>Friend / Referral</option>
            <option>Other</option>
          </select>
        </div>
      </div>
      <div v-if="error" class="error-msg">{{ error }}</div>
      <button type="submit" :disabled="loading" class="btn-submit">
        <span v-if="loading">Sending...</span>
        <span v-else>Get Demo Access →</span>
      </button>
    </form>

  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'

// ── Replace these with your actual keys ──────────────────────────────────────
const EMAILJS_PUBLIC_KEY  = 'YOUR_EMAILJS_PUBLIC_KEY'
const EMAILJS_SERVICE_ID  = 'YOUR_EMAILJS_SERVICE_ID'
const EMAILJS_TEMPLATE_ID = 'YOUR_EMAILJS_TEMPLATE_ID'
const FORMSPREE_ENDPOINT  = 'https://formspree.io/f/YOUR_FORM_ID'
// ─────────────────────────────────────────────────────────────────────────────

const form = reactive({ name: '', email: '', website: '', source: '' })
const loading      = ref(false)
const submitted    = ref(false)
const submittedEmail = ref('')
const error        = ref('')

async function handleSubmit() {
  loading.value = true
  error.value   = ''

  try {
    // 1. Save lead data to Formspree (marketing collection)
    await fetch(FORMSPREE_ENDPOINT, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...form, _subject: 'New FalconCMS Demo Request' }),
    })

    // 2. Send credentials email via EmailJS
    await loadEmailJS()
    await window.emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_TEMPLATE_ID, {
      to_name:    form.name,
      to_email:   form.email,
      demo_url:   'https://demo.falconcms.com',
      demo_email: 'admin@falconcms.com',
      demo_pass:  'password',
    }, EMAILJS_PUBLIC_KEY)

    submittedEmail.value = form.email
    submitted.value = true
  } catch (e) {
    error.value = 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}

function loadEmailJS() {
  return new Promise((resolve) => {
    if (window.emailjs) { resolve(); return }
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js'
    s.onload = resolve
    document.head.appendChild(s)
  })
}

function reset() {
  form.name = ''; form.email = ''; form.website = ''; form.source = ''
  submitted.value = false
  submittedEmail.value = ''
}
</script>

<style scoped>
.demo-form-wrapper { max-width: 720px; margin: 0 auto; }

.demo-form { display: flex; flex-direction: column; gap: 1.25rem; }

.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } }

.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: .875rem; font-weight: 600; color: var(--vp-c-text-1); }
.optional { font-weight: 400; color: var(--vp-c-text-3); font-size: .8rem; }
.req { color: #e53e3e; }

.field input,
.field select {
  padding: 10px 14px;
  border: 1.5px solid var(--vp-c-divider);
  border-radius: 8px;
  font-size: .9rem;
  background: var(--vp-c-bg);
  color: var(--vp-c-text-1);
  transition: border-color .15s;
  width: 100%;
  box-sizing: border-box;
}
.field input:focus,
.field select:focus {
  outline: none;
  border-color: var(--vp-c-brand-1);
}

.btn-submit {
  align-self: flex-start;
  background: var(--vp-c-brand-1);
  color: #fff;
  border: none;
  padding: 11px 28px;
  border-radius: 8px;
  font-size: .95rem;
  font-weight: 700;
  cursor: pointer;
  transition: background .2s, opacity .2s;
}
.btn-submit:hover { background: var(--vp-c-brand-2); }
.btn-submit:disabled { opacity: .6; cursor: not-allowed; }

.error-msg {
  background: #fff5f5;
  border: 1px solid #feb2b2;
  color: #c53030;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: .875rem;
}

.success-box {
  text-align: center;
  padding: 2.5rem 1rem;
}
.success-icon {
  width: 56px; height: 56px;
  background: #48bb78;
  color: #fff;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; font-weight: 700;
  margin: 0 auto 1.25rem;
}
.success-box h3 { font-size: 1.5rem; margin-bottom: .5rem; }
.success-box p  { color: var(--vp-c-text-2); margin-bottom: 1rem; }

.cred-box {
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-divider);
  border-radius: 10px;
  padding: 1.25rem 1.5rem;
  margin: 1.25rem auto;
  max-width: 380px;
  text-align: left;
  font-size: .9rem;
}
.cred-box p { margin: .4rem 0; color: var(--vp-c-text-1); }

.btn-reset {
  background: none;
  border: none;
  color: var(--vp-c-brand-1);
  cursor: pointer;
  font-size: .9rem;
  font-weight: 600;
  margin-top: .5rem;
}
</style>
