import * as React from "react"

const PRICE_PER_REVIEW = 350

// Change this when your dev gives the real endpoint.
// This endpoint should create Stripe checkout/session and return JSON.
const CHECKOUT_ENDPOINT = "https://checkout.solora.ai/review-removal/checkout"

// This is where Stripe/backend should send the user at the end.
const CONFIRMATION_URL = "/confirmation"

export default function ReviewRemovalSection() {
    const [step, setStep] = React.useState(1)

    const [reviews, setReviews] = React.useState([""])
    const [businessName, setBusinessName] = React.useState("")
    const [contactName, setContactName] = React.useState("")
    const [email, setEmail] = React.useState("")
    const [phone, setPhone] = React.useState("")

    const [agreements, setAgreements] = React.useState({
        eligibility: false,
        noInteraction: false,
        authHold: false,
        communications: false,
        terms: false,
    })

    const [submitError, setSubmitError] = React.useState("")
    const [isSubmitting, setIsSubmitting] = React.useState(false)

    const cleanedReviews = React.useMemo(
        () => reviews.map((r) => r.trim()).filter(Boolean),
        [reviews]
    )

    const reviewCount = cleanedReviews.length
    const total = reviewCount * PRICE_PER_REVIEW

    const reviewErrors = reviews.map((review) => {
        const value = review.trim()
        if (!value) return ""
        return isValidHttpUrl(value)
            ? ""
            : "Please enter a valid review URL starting with http:// or https://"
    })

    const hasAtLeastOneReview = cleanedReviews.length > 0
    const hasInvalidReview = reviewErrors.some(Boolean)

    const isStep1Valid = hasAtLeastOneReview && !hasInvalidReview
    const isBusinessNameValid = businessName.trim().length >= 2
    const isContactNameValid = contactName.trim().length >= 2
    const isEmailValid = isValidEmail(email)
    const isPhoneValid = isValidPhone(phone)

    const isStep2Valid =
        isBusinessNameValid &&
        isContactNameValid &&
        isEmailValid &&
        isPhoneValid

    const allAgreementsChecked = Object.values(agreements).every(Boolean)
    const isStep3Valid = isStep1Valid && isStep2Valid && allAgreementsChecked

    const canGoToStep2 = isStep1Valid
    const canGoToStep3 = isStep1Valid && isStep2Valid

    const canGoBackToStep = (targetStep: number) => targetStep < step
    const canGoToStep = (targetStep: number) => {
        if (targetStep === 1) return true
        if (targetStep === 2) return canGoToStep2
        if (targetStep === 3) return canGoToStep3
        return false
    }

    const navigateToStep = (targetStep: number) => {
        if (canGoBackToStep(targetStep)) {
            setStep(targetStep)
            setSubmitError("")
            return
        }

        if (targetStep > step && canGoToStep(targetStep)) {
            setStep(targetStep)
            setSubmitError("")
        }
    }

    const addReview = () => {
        setReviews((prev) => [...prev, ""])
    }

    const removeReview = (index: number) => {
        setReviews((prev) => {
            if (prev.length === 1) return prev
            return prev.filter((_, i) => i !== index)
        })
    }

    const updateReview = (index: number, value: string) => {
        setReviews((prev) => {
            const next = [...prev]
            next[index] = value
            return next
        })
    }

    const toggleAgreement = (key: keyof typeof agreements) => {
        setAgreements((prev) => ({
            ...prev,
            [key]: !prev[key],
        }))
    }

    const getPayload = () => {
        return {
            customer: {
                businessName: businessName.trim(),
                contactName: contactName.trim(),
                email: email.trim(),
                phone: phone.trim(),
            },
            reviewLinks: cleanedReviews,
            order: {
                reviewCount,
                pricePerReview: PRICE_PER_REVIEW,
                total,
                currency: "usd",
            },
            payment: {
                type: "authorization",
                confirmationUrl:
                    typeof window !== "undefined"
                        ? new URL(
                              CONFIRMATION_URL,
                              window.location.origin
                          ).toString()
                        : CONFIRMATION_URL,
            },
            meta: {
                source: "framer-review-removal-form",
                submittedAt: new Date().toISOString(),
            },
        }
    }

    const handleAuthorize = async () => {
        setSubmitError("")

        if (!isStep3Valid || isSubmitting) return

        const payload = getPayload()

        try {
            setIsSubmitting(true)

            const response = await fetch(CHECKOUT_ENDPOINT, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(payload),
            })

            let data: any = null
            try {
                data = await response.json()
            } catch {
                data = null
            }

            if (!response.ok) {
                throw new Error(
                    data?.message ||
                        `Request failed with status ${response.status}`
                )
            }

            // Support multiple possible response shapes from backend/dev.
            const redirectUrl =
                data?.redirectUrl ||
                data?.checkoutUrl ||
                data?.url ||
                data?.sessionUrl

            if (redirectUrl && typeof window !== "undefined") {
                window.location.href = redirectUrl
                return
            }

            // If backend returns success but no URL, send user to confirmation.
            if (typeof window !== "undefined") {
                window.location.href = CONFIRMATION_URL
            }
        } catch (error: any) {
            setSubmitError(
                error?.message ||
                    "Something went wrong while starting checkout. Please try again."
            )
        } finally {
            setIsSubmitting(false)
        }
    }

    return (
        <div style={styles.wrapper}>
            <div style={styles.progressWrap}>
                <StepItem
                    number="1"
                    label="Reviews"
                    active={step === 1}
                    completed={step > 1}
                    clickable={canGoBackToStep(1)}
                    onClick={() => navigateToStep(1)}
                />
                <div
                    style={{
                        ...styles.line,
                        background: step > 1 ? "#E173FF" : "#E7E2EB",
                    }}
                />
                <StepItem
                    number="2"
                    label="Your Info"
                    active={step === 2}
                    completed={step > 2}
                    clickable={canGoBackToStep(2)}
                    onClick={() => navigateToStep(2)}
                />
                <div
                    style={{
                        ...styles.line,
                        background: step > 2 ? "#E173FF" : "#E7E2EB",
                    }}
                />
                <StepItem
                    number="3"
                    label="Payment"
                    active={step === 3}
                    completed={false}
                    clickable={canGoBackToStep(3)}
                    onClick={() => navigateToStep(3)}
                />
            </div>

            <div style={styles.card}>
                {step === 1 && (
                    <>
                        <h2 style={styles.cardTitle}>
                            Paste Your Review Links
                        </h2>
                        <p style={styles.cardSub}>
                            Add the review link(s) you'd like removed — Google,
                            Yelp, or other platforms.
                            <br />
                            <strong style={{ color: "#111111" }}>
                                ${PRICE_PER_REVIEW} per review.
                            </strong>
                        </p>

                        <div style={styles.noticeBox}>
                            <div style={styles.noticeIcon}>i</div>
                            <div style={styles.noticeText}>
                                Only reviews with{" "}
                                <strong>written text or photos</strong> are
                                eligible for removal. Star-only ratings cannot
                                be removed.
                            </div>
                        </div>

                        <div style={styles.helperLinks}>
                            <a href="#" style={styles.helperLink}>
                                How to copy a Google review link →
                            </a>
                            <a href="#" style={styles.helperLink}>
                                How to copy a Yelp review link →
                            </a>
                        </div>

                        <div style={styles.stack16}>
                            {reviews.map((review, index) => {
                                const error = reviewErrors[index]
                                return (
                                    <div key={index} style={styles.stack8}>
                                        <InputRow
                                            value={review}
                                            placeholder="Paste review link (Google, Yelp, etc.)"
                                            onChange={(e) =>
                                                updateReview(
                                                    index,
                                                    e.target.value
                                                )
                                            }
                                            icon={<LinkIcon />}
                                            hasError={Boolean(error)}
                                            rightAction={
                                                reviews.length > 1 ? (
                                                    <button
                                                        type="button"
                                                        style={
                                                            styles.removeChip
                                                        }
                                                        onClick={() =>
                                                            removeReview(index)
                                                        }
                                                    >
                                                        Remove
                                                    </button>
                                                ) : undefined
                                            }
                                        />
                                        {error ? (
                                            <div style={styles.fieldError}>
                                                {error}
                                            </div>
                                        ) : null}
                                    </div>
                                )
                            })}
                        </div>

                        <button
                            type="button"
                            style={styles.addButton}
                            onClick={addReview}
                        >
                            <span style={styles.addPlus}>+</span>
                            <span>Add Another Review</span>
                        </button>

                        {!hasAtLeastOneReview ? (
                            <div
                                style={{ ...styles.fieldError, marginTop: 12 }}
                            >
                                Add at least one valid review link to continue.
                            </div>
                        ) : null}

                        <div style={styles.bottomRow}>
                            <div style={styles.reviewCount}>
                                <strong>
                                    {reviewCount} review
                                    {reviewCount !== 1 ? "s" : ""}
                                </strong>{" "}
                                · ${total}
                            </div>

                            <button
                                type="button"
                                style={{
                                    ...styles.primaryButton,
                                    ...(isStep1Valid
                                        ? styles.primaryButtonActive
                                        : styles.primaryButtonDisabled),
                                }}
                                onClick={() => navigateToStep(2)}
                                disabled={!isStep1Valid}
                            >
                                Continue <span style={styles.arrow}>→</span>
                            </button>
                        </div>
                    </>
                )}

                {step === 2 && (
                    <>
                        <h2 style={styles.cardTitle}>Your Information</h2>
                        <p style={styles.cardSub}>
                            Tell us about your business so we can process the
                            removal.
                        </p>

                        <div style={styles.formStack}>
                            <FieldLabel label="Business Name" />
                            <InputRow
                                value={businessName}
                                onChange={(e) =>
                                    setBusinessName(e.target.value)
                                }
                                placeholder="Business name"
                                icon={<BusinessIcon />}
                                hasError={
                                    businessName.length > 0 &&
                                    !isBusinessNameValid
                                }
                            />
                            {businessName.length > 0 && !isBusinessNameValid ? (
                                <div style={styles.fieldError}>
                                    Please enter a valid business name.
                                </div>
                            ) : null}

                            <FieldLabel label="Contact Name" />
                            <InputRow
                                value={contactName}
                                onChange={(e) => setContactName(e.target.value)}
                                placeholder="Full name"
                                icon={<PersonIcon />}
                                hasError={
                                    contactName.length > 0 &&
                                    !isContactNameValid
                                }
                            />
                            {contactName.length > 0 && !isContactNameValid ? (
                                <div style={styles.fieldError}>
                                    Please enter the contact person’s name.
                                </div>
                            ) : null}

                            <FieldLabel label="Email" />
                            <InputRow
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="name@company.com"
                                icon={<MailIcon />}
                                hasError={email.length > 0 && !isEmailValid}
                                inputMode="email"
                            />
                            {email.length > 0 && !isEmailValid ? (
                                <div style={styles.fieldError}>
                                    Please enter a valid email address.
                                </div>
                            ) : null}

                            <FieldLabel label="Phone" />
                            <InputRow
                                value={phone}
                                onChange={(e) => setPhone(e.target.value)}
                                placeholder="+1 (555) 123-4567"
                                icon={<PhoneIcon />}
                                hasError={phone.length > 0 && !isPhoneValid}
                                inputMode="tel"
                            />
                            {phone.length > 0 && !isPhoneValid ? (
                                <div style={styles.fieldError}>
                                    Please enter a valid phone number.
                                </div>
                            ) : null}
                        </div>

                        <div style={styles.bottomRow}>
                            <button
                                type="button"
                                style={styles.backButton}
                                onClick={() => navigateToStep(1)}
                            >
                                ← <span style={{ marginLeft: 8 }}>Back</span>
                            </button>

                            <button
                                type="button"
                                style={{
                                    ...styles.primaryButton,
                                    ...(isStep2Valid
                                        ? styles.primaryButtonActive
                                        : styles.primaryButtonDisabled),
                                }}
                                onClick={() => navigateToStep(3)}
                                disabled={!isStep2Valid}
                            >
                                Continue <span style={styles.arrow}>→</span>
                            </button>
                        </div>
                    </>
                )}

                {step === 3 && (
                    <>
                        <h2 style={styles.cardTitle}>Authorize Payment</h2>
                        <p style={styles.cardSub}>
                            A hold will be placed on your card. You&apos;re only
                            charged once the review is successfully removed.
                        </p>

                        <div style={styles.summaryBox}>
                            <div style={styles.summaryTop}>
                                <span style={styles.summaryMuted}>
                                    Review removal
                                </span>
                                <span style={styles.summaryPrice}>
                                    {reviewCount} x ${PRICE_PER_REVIEW}
                                </span>
                            </div>
                            <div style={styles.summaryDivider} />
                            <div style={styles.summaryBottom}>
                                <span style={styles.totalLabel}>Total</span>
                                <span style={styles.totalPrice}>${total}</span>
                            </div>
                        </div>

                        <div style={styles.checkList}>
                            <CheckItem
                                checked={agreements.eligibility}
                                onChange={() => toggleAgreement("eligibility")}
                                text="The review(s) I’ve submitted include written text and/or an image and are not star-only ratings."
                            />
                            <CheckItem
                                checked={agreements.noInteraction}
                                onChange={() =>
                                    toggleAgreement("noInteraction")
                                }
                                text="I agree not to interact with the review(s) in any way while Solora is working on the removal."
                            />
                            <CheckItem
                                checked={agreements.authHold}
                                onChange={() => toggleAgreement("authHold")}
                                text={`I understand that an authorization hold of $${total} ($${PRICE_PER_REVIEW} per review) will be placed on my card. I will only be charged once the review is successfully removed — otherwise, the hold will be released.`}
                            />
                            <CheckItem
                                checked={agreements.communications}
                                onChange={() =>
                                    toggleAgreement("communications")
                                }
                                text='I agree that Solora may call, text, or email me with updates about my removal process. Msg & data rates may apply. Reply "BYE" to end communications at any time.'
                            />
                            <CheckItem
                                checked={agreements.terms}
                                onChange={() => toggleAgreement("terms")}
                                text={
                                    <>
                                        I have read and agree to the{" "}
                                        <a href="#" style={styles.inlineLink}>
                                            Terms of Service
                                        </a>
                                        .
                                    </>
                                }
                            />
                        </div>

                        <div style={styles.trustPoints}>
                            <TrustItem
                                icon={<ShieldIcon />}
                                text={
                                    <>
                                        <strong>No-risk guarantee</strong> —
                                        only charged upon successful removal
                                    </>
                                }
                            />
                            <TrustItem
                                icon={<ClockIcon />}
                                text={
                                    <>
                                        Removal typically takes{" "}
                                        <strong>1–3 weeks</strong>
                                    </>
                                }
                            />
                            <TrustItem
                                icon={<CardIcon />}
                                text={<>Secure payment processing via Stripe</>}
                            />
                        </div>

                        {submitError ? (
                            <div style={styles.submitError}>{submitError}</div>
                        ) : null}

                        <div style={styles.bottomRow}>
                            <button
                                type="button"
                                style={styles.backButton}
                                onClick={() => navigateToStep(2)}
                                disabled={isSubmitting}
                            >
                                ← <span style={{ marginLeft: 8 }}>Back</span>
                            </button>

                            <button
                                type="button"
                                style={{
                                    ...styles.primaryButton,
                                    ...(isStep3Valid && !isSubmitting
                                        ? styles.primaryButtonActive
                                        : styles.primaryButtonDisabled),
                                }}
                                onClick={handleAuthorize}
                                disabled={!isStep3Valid || isSubmitting}
                            >
                                <span style={{ marginRight: 10 }}>
                                    <CardMiniIcon />
                                </span>
                                {isSubmitting
                                    ? "Redirecting..."
                                    : `Authorize $${total}`}
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    )
}

function StepItem({
    number,
    label,
    active,
    completed,
    clickable,
    onClick,
}: {
    number: string
    label: string
    active?: boolean
    completed?: boolean
    clickable?: boolean
    onClick?: () => void
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            style={{
                ...styles.stepItemButton,
                cursor: clickable ? "pointer" : "default",
            }}
            aria-label={`Go to ${label}`}
        >
            <div
                style={{
                    ...styles.stepCircle,
                    background: active || completed ? "#E173FF" : "#F1EEF4",
                    color: active || completed ? "#ffffff" : "#6D6671",
                    boxShadow: active
                        ? "0 10px 24px rgba(225, 115, 255, 0.28)"
                        : "none",
                }}
            >
                {completed ? "✓" : number}
            </div>
            <div style={styles.stepLabel}>{label}</div>
        </button>
    )
}

function FieldLabel({ label }: { label: string }) {
    return <div style={styles.fieldLabel}>{label}</div>
}

function InputRow({
    value,
    placeholder,
    icon,
    onChange,
    hasError,
    rightAction,
    inputMode,
}: {
    value: string
    placeholder: string
    icon: React.ReactNode
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void
    hasError?: boolean
    rightAction?: React.ReactNode
    inputMode?: React.HTMLAttributes<HTMLInputElement>["inputMode"]
}) {
    return (
        <div
            style={{
                ...styles.inputWrap,
                border: hasError ? "1px solid #E97B7B" : "1px solid #D9D3DB",
                background: "#FAF8FB",
            }}
        >
            <div style={styles.inputIcon}>{icon}</div>
            <input
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                style={styles.input}
                autoComplete="off"
                autoCorrect="off"
                autoCapitalize="off"
                spellCheck={false}
                inputMode={inputMode}
            />
            {rightAction ? (
                <div style={styles.inputRightAction}>{rightAction}</div>
            ) : null}
        </div>
    )
}

function CheckItem({
    text,
    checked,
    onChange,
}: {
    text: React.ReactNode
    checked: boolean
    onChange: () => void
}) {
    return (
        <label style={styles.checkItem}>
            <input
                type="checkbox"
                checked={checked}
                onChange={onChange}
                style={styles.hiddenCheckbox}
            />
            <div
                style={{
                    ...styles.checkboxCircle,
                    background: checked ? "#7C3AED" : "#FFFFFF",
                    borderColor: checked ? "#7C3AED" : "#CFC7D6",
                }}
            >
                {checked ? <CheckMarkIcon /> : null}
            </div>
            <div style={styles.checkText}>{text}</div>
        </label>
    )
}

function TrustItem({
    icon,
    text,
}: {
    icon: React.ReactNode
    text: React.ReactNode
}) {
    return (
        <div style={styles.trustItem}>
            <div style={styles.trustIcon}>{icon}</div>
            <div style={styles.trustText}>{text}</div>
        </div>
    )
}

function isValidHttpUrl(value: string) {
    try {
        const url = new URL(value)
        return url.protocol === "http:" || url.protocol === "https:"
    } catch {
        return false
    }
}

function isValidEmail(value: string) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

function isValidPhone(value: string) {
    const cleaned = value.replace(/[^\d+]/g, "")
    const digits = cleaned.replace(/\D/g, "")
    return digits.length >= 7
}

function LinkIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path
                d="M10 13a5 5 0 0 1 0-7l1.5-1.5a5 5 0 0 1 7 7L17 13"
                stroke="#6F6874"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <path
                d="M14 11a5 5 0 0 1 0 7L12.5 19.5a5 5 0 0 1-7-7L7 11"
                stroke="#6F6874"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
        </svg>
    )
}

function BusinessIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <rect
                x="4"
                y="5"
                width="10"
                height="14"
                rx="2"
                stroke="#6F6874"
                strokeWidth="1.6"
            />
            <path
                d="M14 9h4a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-4"
                stroke="#6F6874"
                strokeWidth="1.6"
            />
            <path
                d="M8 9h2M8 12h2M8 15h2"
                stroke="#6F6874"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
        </svg>
    )
}

function PersonIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="3.2" stroke="#6F6874" strokeWidth="1.6" />
            <path
                d="M6.5 18.2c1.3-2.8 3.2-4.2 5.5-4.2s4.2 1.4 5.5 4.2"
                stroke="#6F6874"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
        </svg>
    )
}

function MailIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <rect
                x="3.5"
                y="5.5"
                width="17"
                height="13"
                rx="2"
                stroke="#6F6874"
                strokeWidth="1.6"
            />
            <path
                d="M5 8l7 5 7-5"
                stroke="#6F6874"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}

function PhoneIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path
                d="M8.4 4.8l2.1 3.8c.3.5.2 1.1-.2 1.6l-1.2 1.2a14.7 14.7 0 0 0 3.5 3.5l1.2-1.2c.4-.4 1-.5 1.6-.2l3.8 2.1c.8.4 1 1.5.4 2.2l-1.1 1.4c-.5.6-1.3.9-2.1.8-3.3-.5-6.5-2.1-9.2-4.8-2.7-2.7-4.3-5.9-4.8-9.2-.1-.8.2-1.6.8-2.1L6.2 4.4c.7-.6 1.8-.4 2.2.4Z"
                stroke="#6F6874"
                strokeWidth="1.6"
                strokeLinejoin="round"
            />
        </svg>
    )
}

function ShieldIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path
                d="M12 3l7 3v6c0 4.4-2.8 7.7-7 9-4.2-1.3-7-4.6-7-9V6l7-3Z"
                stroke="#7C3AED"
                strokeWidth="1.8"
            />
        </svg>
    )
}

function ClockIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle
                cx="12"
                cy="12"
                r="8.5"
                stroke="#7C3AED"
                strokeWidth="1.8"
            />
            <path
                d="M12 7.8v4.7l3 1.8"
                stroke="#7C3AED"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
        </svg>
    )
}

function CardIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <rect
                x="3"
                y="6"
                width="18"
                height="12"
                rx="2.2"
                stroke="#7C3AED"
                strokeWidth="1.8"
            />
            <path d="M3 10h18" stroke="#7C3AED" strokeWidth="1.8" />
        </svg>
    )
}

function CardMiniIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <rect
                x="3"
                y="6"
                width="18"
                height="12"
                rx="2.2"
                stroke="#FFFFFF"
                strokeWidth="1.8"
            />
            <path d="M3 10h18" stroke="#FFFFFF" strokeWidth="1.8" />
        </svg>
    )
}

function CheckMarkIcon() {
    return (
        <svg width="10" height="10" viewBox="0 0 12 12" fill="none">
            <path
                d="M2.2 6.3 4.7 8.8 9.8 3.7"
                stroke="#FFFFFF"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}

const styles: { [key: string]: React.CSSProperties } = {
    wrapper: {
        width: "100%",
        maxWidth: 760,
        margin: "0 auto",
        paddingBottom: 40,
        fontFamily:
            'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        color: "#111111",
    },

    progressWrap: {
        display: "flex",
        alignItems: "flex-start",
        justifyContent: "center",
        gap: 0,
        marginBottom: 34,
        paddingTop: 8,
        flexWrap: "nowrap",
    },

    stepItemButton: {
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        minWidth: 78,
        background: "transparent",
        border: "none",
        padding: 0,
    },

    stepCircle: {
        width: 48,
        height: 48,
        borderRadius: 999,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        fontSize: 22,
        fontWeight: 700,
        lineHeight: 1,
        transition: "all 0.2s ease",
    },

    stepLabel: {
        marginTop: 10,
        fontSize: 14,
        fontWeight: 500,
        color: "#111111",
        fontFamily:
            'Poppins, Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    },

    line: {
        width: 112,
        height: 3,
        borderRadius: 999,
        marginTop: 22,
        marginLeft: 12,
        marginRight: 12,
        transition: "background 0.2s ease",
    },
    card: {
        position: "relative",
        background: "linear-gradient(180deg, #FFFFFF 0%, #FCFCFD 100%)",
        border: "1px solid rgba(225, 220, 230, 0.95)",
        borderRadius: 24,
        padding: "36px 32px 30px",
        boxSizing: "border-box",
        boxShadow:
            "0 8px 20px rgba(17,17,17,0.04), 0 24px 50px rgba(17,17,17,0.05), 0 55px 90px rgba(124, 58, 237, 0.10), 0 70px 110px rgba(59, 130, 246, 0.08)",
        overflow: "visible",
    },

    inputWrap: {
        display: "flex",
        alignItems: "center",
        borderRadius: 14,
        height: 54,
        padding: "0 14px",
        boxSizing: "border-box",
        transition: "border-color 0.2s ease, box-shadow 0.2s ease",
        boxShadow: "0 4px 14px rgba(17,17,17,0.03)",
    },
    summaryBox: {
        marginTop: 24,
        background: "linear-gradient(180deg, #FAF7FD 0%, #F6F2FA 100%)",
        border: "1px solid #E5DDF0",
        borderRadius: 18,
        padding: "18px 20px",
        boxShadow: "0 12px 30px rgba(124, 58, 237, 0.06)",
    },

    cardTitle: {
        margin: 0,
        textAlign: "center",
        fontSize: 24,
        lineHeight: 1.2,
        fontWeight: 500,
        color: "#111111",
        fontFamily:
            'Poppins, Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    },

    cardSub: {
        margin: "14px 0 0",
        textAlign: "center",
        fontSize: 16,
        lineHeight: 1.55,
        color: "#6C6670",
    },

    noticeBox: {
        marginTop: 22,
        background: "#F5F1FA",
        border: "1px solid #E7DDF5",
        borderRadius: 14,
        padding: "16px 18px",
        display: "flex",
        alignItems: "flex-start",
        gap: 12,
    },

    noticeIcon: {
        width: 18,
        height: 18,
        minWidth: 18,
        borderRadius: 999,
        border: "1.6px solid #7C3AED",
        color: "#7C3AED",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        fontSize: 11,
        fontWeight: 700,
        marginTop: 2,
    },

    noticeText: {
        fontSize: 15,
        lineHeight: 1.5,
        color: "#6C6670",
    },

    helperLinks: {
        display: "flex",
        gap: 22,
        flexWrap: "wrap",
        marginTop: 22,
        marginBottom: 20,
    },

    helperLink: {
        fontSize: 14,
        color: "#7C3AED",
        textDecoration: "none",
        fontWeight: 500,
    },

    stack16: {
        display: "flex",
        flexDirection: "column",
        gap: 16,
    },

    stack8: {
        display: "flex",
        flexDirection: "column",
        gap: 8,
    },

    inputWrap: {
        display: "flex",
        alignItems: "center",
        borderRadius: 12,
        height: 52,
        padding: "0 14px",
        boxSizing: "border-box",
        transition: "border-color 0.2s ease, box-shadow 0.2s ease",
    },

    inputIcon: {
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        marginRight: 12,
        color: "#6F6874",
    },

    input: {
        flex: 1,
        border: "none",
        outline: "none",
        background: "transparent",
        fontSize: 15,
        color: "#111111",
        fontFamily: "inherit",
        minWidth: 0,
    },

    inputRightAction: {
        marginLeft: 10,
    },

    removeChip: {
        border: "none",
        background: "#EFE9F8",
        color: "#6E34D0",
        fontSize: 12,
        fontWeight: 700,
        borderRadius: 999,
        padding: "8px 10px",
        cursor: "pointer",
    },

    fieldError: {
        fontSize: 13,
        lineHeight: 1.45,
        color: "#C24B4B",
    },

    submitError: {
        marginTop: 18,
        padding: "12px 14px",
        background: "#FFF3F3",
        border: "1px solid #F2C9C9",
        borderRadius: 12,
        color: "#B53F3F",
        fontSize: 14,
        lineHeight: 1.5,
    },

    addButton: {
        width: "100%",
        height: 50,
        marginTop: 18,
        borderRadius: 12,
        border: "2px dashed #DDD7DF",
        background: "transparent",
        color: "#726C76",
        fontSize: 16,
        fontWeight: 500,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        gap: 10,
        cursor: "pointer",
    },

    addPlus: {
        fontSize: 24,
        lineHeight: 1,
        color: "#8A848E",
        marginTop: -1,
    },

    bottomRow: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 16,
        marginTop: 28,
        flexWrap: "wrap",
    },

    reviewCount: {
        fontSize: 15,
        color: "#6C6670",
    },

    primaryButton: {
        height: 48,
        minWidth: 170,
        border: "none",
        borderRadius: 999,
        color: "#FFFFFF",
        fontSize: 15,
        fontWeight: 700,
        padding: "0 26px",
        cursor: "pointer",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        transition: "all 0.2s ease",
    },

    primaryButtonActive: {
        background: "#00C8FF",
        boxShadow: "0 14px 28px rgba(0, 200, 255, 0.24)",
    },

    primaryButtonDisabled: {
        background: "#A3A3A8",
        boxShadow: "none",
        cursor: "not-allowed",
    },

    arrow: {
        marginLeft: 10,
        fontSize: 18,
        lineHeight: 1,
    },

    formStack: {
        marginTop: 26,
        display: "flex",
        flexDirection: "column",
        gap: 12,
    },

    fieldLabel: {
        marginTop: 4,
        marginBottom: -2,
        fontSize: 15,
        fontWeight: 600,
        color: "#111111",
    },

    backButton: {
        border: "none",
        background: "transparent",
        color: "#6B6570",
        fontSize: 15,
        fontWeight: 500,
        cursor: "pointer",
        display: "flex",
        alignItems: "center",
        padding: 0,
    },

    summaryBox: {
        marginTop: 24,
        background: "#F6F2FA",
        border: "1px solid #E5DDF0",
        borderRadius: 14,
        padding: "18px 20px",
    },

    summaryTop: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 16,
    },

    summaryMuted: {
        fontSize: 16,
        color: "#7D7681",
    },

    summaryPrice: {
        fontSize: 16,
        color: "#111111",
        fontWeight: 500,
    },

    summaryDivider: {
        height: 1,
        background: "#E4DEE6",
        margin: "16px 0",
    },

    summaryBottom: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 16,
    },

    totalLabel: {
        fontSize: 18,
        fontWeight: 700,
        color: "#111111",
    },

    totalPrice: {
        fontSize: 24,
        fontWeight: 800,
        color: "#7C3AED",
    },

    checkList: {
        marginTop: 24,
        display: "flex",
        flexDirection: "column",
        gap: 18,
    },

    checkItem: {
        display: "flex",
        alignItems: "flex-start",
        gap: 12,
        cursor: "pointer",
    },

    hiddenCheckbox: {
        position: "absolute",
        opacity: 0,
        pointerEvents: "none",
        width: 0,
        height: 0,
    },

    checkboxCircle: {
        width: 18,
        height: 18,
        minWidth: 18,
        borderRadius: 999,
        border: "1.8px solid #CFC7D6",
        marginTop: 2,
        boxSizing: "border-box",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
    },

    checkText: {
        fontSize: 15,
        lineHeight: 1.5,
        color: "#5F5964",
    },

    inlineLink: {
        color: "#7C3AED",
        textDecoration: "underline",
    },

    trustPoints: {
        marginTop: 24,
        display: "flex",
        flexDirection: "column",
        gap: 14,
    },

    trustItem: {
        display: "flex",
        alignItems: "flex-start",
        gap: 10,
    },

    trustIcon: {
        minWidth: 16,
        marginTop: 2,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
    },

    trustText: {
        fontSize: 15,
        lineHeight: 1.5,
        color: "#5F5964",
    },
}
