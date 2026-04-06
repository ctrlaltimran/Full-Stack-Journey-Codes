import * as React from "react"
import { createPortal } from "react-dom"

const PRICE_PER_REVIEW = 350
const CHECKOUT_ENDPOINT = "https://checkout.solora.ai/review-removal/checkout"
const CONFIRMATION_URL = "/confirmation"

const HELP_STEPS = [
    {
        title: "Go to the Review Platform",
        body: "Open the platform where the review was posted. Navigate to your business listing.",
        visual: "platforms",
    },
    {
        title: "Search for Your Business",
        body: "Type your business name in the search bar and press Enter.",
        visual: "search",
    },
    {
        title: "Open the Reviews",
        body: "Click on your business listing, then find the reviews section.",
        visual: "reviews",
    },
    {
        title: "Find the Review",
        body: "Scroll to the review you want removed and click the three dots (⋮) or menu icon.",
        visual: "menu",
    },
    {
        title: 'Click "Share Review"',
        body: 'From the menu, select "Share review" or a similar sharing option.',
        visual: "share",
    },
    {
        title: "Copy the Link",
        body: 'Click "Copy link" to copy the review URL to your clipboard. Then paste it here!',
        visual: "copy",
    },
] as const

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

    const [showHelp, setShowHelp] = React.useState(false)
    const [helpStep, setHelpStep] = React.useState(0)
    const [hoveredHintIndex, setHoveredHintIndex] = React.useState<
        number | null
    >(null)
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

    const navigateToStep = (targetStep: number) => {
        if (targetStep < step) {
            setStep(targetStep)
            setSubmitError("")
            return
        }
        if (targetStep === 2 && isStep1Valid) {
            setStep(2)
            setSubmitError("")
        }
        if (targetStep === 3 && isStep1Valid && isStep2Valid) {
            setStep(3)
            setSubmitError("")
        }
    }

    const addReview = () => setReviews((prev) => [...prev, ""])

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
        setAgreements((prev) => ({ ...prev, [key]: !prev[key] }))
    }

    const handlePhoneChange = (rawValue: string) => {
        setPhone(formatPhoneLikeScreenshot(rawValue, phone))
    }

    const getPayload = () => ({
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
    })

    const handleAuthorize = async () => {
        setSubmitError("")
        if (!isStep3Valid || isSubmitting) return

        try {
            setIsSubmitting(true)
            const response = await fetch(CHECKOUT_ENDPOINT, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(getPayload()),
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

            const redirectUrl =
                data?.redirectUrl ||
                data?.checkoutUrl ||
                data?.url ||
                data?.sessionUrl

            if (redirectUrl && typeof window !== "undefined") {
                window.location.href = redirectUrl
                return
            }

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

    const activeHelp = HELP_STEPS[helpStep]

    return (
        <>
            <style>{`
                    @keyframes fadeSlideIn {
                        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap");

                        from {
                            opacity: 0;
                            transform: translateY(10px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    @keyframes fadeScaleIn {
                        from {
                            opacity: 0;
                            transform: translateY(10px) scale(.96);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0) scale(1);
                        }
                    }

                    @keyframes pillReveal {
                        0% {
                            opacity: 0;
                            transform: translateY(14px) scale(.96);
                        }
                        100% {
                            opacity: 1;
                            transform: translateY(0) scale(1);
                        }
                    }

                    @keyframes shimmerMove {
                        0% { transform: translateX(-140%); }
                        100% { transform: translateX(160%); }
                    }

                    @keyframes pulseGlow {
                        0%, 100% {
                            transform: scale(1);
                            box-shadow: 0 0 0 0 rgba(181,77,255,.28);
                        }
                        50% {
                            transform: scale(1.08);
                            box-shadow: 0 0 0 10px rgba(181,77,255,0);
                        }
                    }

                    @keyframes typingBar {
                        from { width: 0%; }
                        to { width: 72%; }
                    }
                    @keyframes typingText {
    from { width: 0ch; }
    to { width: 18ch; }
}

                    @keyframes blinkCaret {
                        0%, 49% { opacity: 1; }
                        50%, 100% { opacity: 0; }
                    }

                    @keyframes slideInUp {
                        from {
                            opacity: 0;
                            transform: translateY(16px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    @keyframes menuDrop {
                        from {
                            opacity: 0;
                            transform: translateY(-10px) scale(.96);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0) scale(1);
                        }
                    }

                    @keyframes softFloat {
                        0%, 100% { transform: translateY(0px); }
                        50% { transform: translateY(-4px); }
                    }

                    @keyframes tabPulse {
                        0%, 100% { opacity: 1; transform: scale(1); }
                        50% { opacity: .88; transform: scale(1.04); }
                    }

                    @keyframes copyFlash {
                        0% { transform: scale(.96); opacity: 0; }
                        100% { transform: scale(1); opacity: 1; }
                    }
                `}</style>

            <div style={styles.wrapper}>
                <div style={styles.progressWrap}>
                    <TopStep
                        number="1"
                        label="Reviews"
                        active={step === 1}
                        completed={step > 1}
                        onClick={() => navigateToStep(1)}
                    />
                    <div
                        style={{
                            ...styles.topLine,
                            opacity: step > 1 ? 1 : 0.35,
                        }}
                    />
                    <TopStep
                        number="2"
                        label="Your Info"
                        active={step === 2}
                        completed={step > 2}
                        onClick={() => navigateToStep(2)}
                    />
                    <div
                        style={{
                            ...styles.topLine,
                            opacity: step > 2 ? 1 : 0.35,
                        }}
                    />
                    <TopStep
                        number="3"
                        label="Payment"
                        active={step === 3}
                        completed={false}
                        onClick={() => navigateToStep(3)}
                    />
                </div>

                <div style={styles.card}>
                    {step === 1 && (
                        <div style={styles.stepFade}>
                            <h2 style={styles.cardTitle}>
                                Paste Your Review Links
                            </h2>
                            <p style={styles.cardSub}>
                                Paste the link to each review you&apos;d like
                                removed.
                            </p>

                            <div style={styles.noticeBox}>
                                <div style={styles.noticeLeftIcon}>
                                    <InfoCircleIcon />
                                </div>
                                <div style={styles.noticeText}>
                                    Only reviews with{" "}
                                    <strong>written text or photos</strong> are
                                    eligible. Star-only ratings cannot be
                                    removed.
                                </div>
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
                                                    <div
                                                        style={
                                                            styles.inputActions
                                                        }
                                                    >
                                                        {reviews.length > 1 ? (
                                                            <button
                                                                type="button"
                                                                style={
                                                                    styles.miniRemove
                                                                }
                                                                onClick={() =>
                                                                    removeReview(
                                                                        index
                                                                    )
                                                                }
                                                            >
                                                                ×
                                                            </button>
                                                        ) : null}
                                                        <div
                                                            style={
                                                                styles.helpWrap
                                                            }
                                                            onMouseEnter={() =>
                                                                setHoveredHintIndex(
                                                                    index
                                                                )
                                                            }
                                                            onMouseLeave={() =>
                                                                setHoveredHintIndex(
                                                                    null
                                                                )
                                                            }
                                                        >
                                                            <button
                                                                type="button"
                                                                style={
                                                                    styles.helpIconButton
                                                                }
                                                                onClick={() => {
                                                                    setHelpStep(
                                                                        0
                                                                    )
                                                                    setShowHelp(
                                                                        true
                                                                    )
                                                                }}
                                                            >
                                                                <QuestionCircleIcon />
                                                            </button>
                                                            {hoveredHintIndex ===
                                                            index ? (
                                                                <div
                                                                    style={
                                                                        styles.tooltip
                                                                    }
                                                                >
                                                                    <div
                                                                        style={
                                                                            styles.tooltipArrow
                                                                        }
                                                                    />
                                                                    Only reviews
                                                                    with written
                                                                    text or
                                                                    photos are
                                                                    eligible.
                                                                    Click for
                                                                    help finding
                                                                    your link.
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                    </div>
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
                                style={styles.addAnotherButton}
                                onClick={addReview}
                            >
                                <span style={styles.addPlus}>+</span> Add
                                another
                            </button>

                            <div style={styles.bottomRow}>
                                <div style={styles.priceNote}>
                                    ${PRICE_PER_REVIEW} per review
                                </div>
                                <button
                                    type="button"
                                    style={{
                                        ...styles.primaryButton,
                                        ...(isStep1Valid
                                            ? styles.primaryButtonEnabled
                                            : styles.primaryButtonDisabled),
                                    }}
                                    onClick={() => navigateToStep(2)}
                                    disabled={!isStep1Valid}
                                >
                                    Continue <ArrowRightIcon />
                                </button>
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div style={styles.stepFade}>
                            <h2 style={styles.cardTitle}>Your Information</h2>
                            <p style={styles.cardSub}>
                                Tell us about your business so we can process
                                the removal.
                            </p>

                            <div style={styles.formStack}>
                                <FieldLabel label="Business Name" />
                                <InputRow
                                    value={businessName}
                                    onChange={(e) =>
                                        setBusinessName(e.target.value)
                                    }
                                    placeholder="Acme Inc."
                                    icon={<BusinessIcon />}
                                    hasError={
                                        businessName.length > 0 &&
                                        !isBusinessNameValid
                                    }
                                />

                                <FieldLabel label="Contact Name" />
                                <InputRow
                                    value={contactName}
                                    onChange={(e) =>
                                        setContactName(e.target.value)
                                    }
                                    placeholder="John Doe"
                                    icon={<PersonIcon />}
                                    hasError={
                                        contactName.length > 0 &&
                                        !isContactNameValid
                                    }
                                />

                                <FieldLabel label="Email" />
                                <InputRow
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="john@acme.com"
                                    icon={<MailIcon />}
                                    inputMode="email"
                                    hasError={email.length > 0 && !isEmailValid}
                                />

                                <FieldLabel label="Phone" />
                                <InputRow
                                    value={phone}
                                    onChange={(e) =>
                                        handlePhoneChange(e.target.value)
                                    }
                                    placeholder="(312) 398-7124"
                                    icon={<PhoneIcon />}
                                    inputMode="tel"
                                    hasError={phone.length > 0 && !isPhoneValid}
                                    focusedBorder
                                />
                            </div>

                            <div style={styles.bottomRow}>
                                <button
                                    type="button"
                                    style={styles.backButton}
                                    onClick={() => navigateToStep(1)}
                                >
                                    <ArrowLeftIcon />{" "}
                                    <span style={{ marginLeft: 10 }}>Back</span>
                                </button>
                                <button
                                    type="button"
                                    style={{
                                        ...styles.primaryButton,
                                        ...(isStep2Valid
                                            ? styles.primaryButtonEnabled
                                            : styles.primaryButtonDisabled),
                                    }}
                                    onClick={() => navigateToStep(3)}
                                    disabled={!isStep2Valid}
                                >
                                    Continue <ArrowRightIcon />
                                </button>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div style={styles.stepFade}>
                            <h2 style={styles.cardTitle}>Authorize Payment</h2>
                            <p style={styles.cardSub}>
                                A hold will be placed on your card. You&apos;re
                                only charged once the review is successfully
                                removed.
                            </p>

                            <div style={styles.summaryBox}>
                                <div style={styles.summaryTop}>
                                    <span style={styles.summaryMuted}>
                                        Review removal
                                    </span>
                                    <span style={styles.summaryPrice}>
                                        {reviewCount} × ${PRICE_PER_REVIEW}
                                    </span>
                                </div>
                                <div style={styles.summaryDivider} />
                                <div style={styles.summaryBottom}>
                                    <span style={styles.totalLabel}>Total</span>
                                    <span style={styles.totalPrice}>
                                        ${total}
                                    </span>
                                </div>
                            </div>

                            <div style={styles.checkList}>
                                <CheckItem
                                    checked={agreements.eligibility}
                                    onChange={() =>
                                        toggleAgreement("eligibility")
                                    }
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
                                            <a
                                                href="#"
                                                style={styles.inlineLink}
                                            >
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
                                            <span>
                                                Removal typically takes{" "}
                                            </span>
                                            <strong>1–3 weeks</strong>
                                        </>
                                    }
                                />
                                <TrustItem
                                    icon={<CardIcon />}
                                    text={
                                        <>
                                            Secure payment processing via Stripe
                                        </>
                                    }
                                />
                            </div>

                            {submitError ? (
                                <div style={styles.submitError}>
                                    {submitError}
                                </div>
                            ) : null}

                            <div style={styles.bottomRow}>
                                <button
                                    type="button"
                                    style={styles.backButton}
                                    onClick={() => navigateToStep(2)}
                                >
                                    <ArrowLeftIcon />{" "}
                                    <span style={{ marginLeft: 10 }}>Back</span>
                                </button>
                                <button
                                    type="button"
                                    style={{
                                        ...styles.primaryButton,
                                        ...(isStep3Valid && !isSubmitting
                                            ? styles.primaryButtonEnabled
                                            : styles.primaryButtonDisabled),
                                    }}
                                    onClick={handleAuthorize}
                                    disabled={!isStep3Valid || isSubmitting}
                                >
                                    <CardMiniIcon />
                                    <span style={{ marginLeft: 10 }}>
                                        {isSubmitting
                                            ? "Redirecting..."
                                            : `Authorize $${total}`}
                                    </span>
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {showHelp ? (
                <HelpModal
                    step={helpStep}
                    title={activeHelp.title}
                    body={activeHelp.body}
                    visual={activeHelp.visual}
                    onClose={() => setShowHelp(false)}
                    onNext={() => {
                        if (helpStep < HELP_STEPS.length - 1)
                            setHelpStep((s) => s + 1)
                        else setShowHelp(false)
                    }}
                    onBack={() => setHelpStep((s) => Math.max(0, s - 1))}
                />
            ) : null}
        </>
    )
}

function TopStep({
    number,
    label,
    active,
    completed,
    onClick,
}: {
    number: string
    label: string
    active?: boolean
    completed?: boolean
    onClick?: () => void
}) {
    return (
        <button type="button" onClick={onClick} style={styles.topStepBtn}>
            <div
                style={{
                    ...styles.topStepCircle,
                    background: active || completed ? "#111111" : "#ECE7EF",
                    color: active || completed ? "#FFFFFF" : "#68616C",
                }}
            >
                {completed ? "✓" : number}
            </div>
            <div style={styles.topStepLabel}>{label}</div>
        </button>
    )
}

function HelpModal({
    step,
    title,
    body,
    visual,
    onClose,
    onNext,
    onBack,
}: {
    step: number
    title: string
    body: string
    visual: string
    onClose: () => void
    onNext: () => void
    onBack: () => void
}) {
    React.useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === "Escape") onClose()
        }

        const scrollY = window.scrollY

        document.body.style.overflow = "hidden"
        document.body.style.position = "fixed"
        document.body.style.top = `-${scrollY}px`
        document.body.style.left = "0"
        document.body.style.right = "0"
        document.body.style.width = "100%"

        window.addEventListener("keydown", onKey)

        return () => {
            window.removeEventListener("keydown", onKey)

            document.body.style.overflow = ""
            document.body.style.position = ""
            document.body.style.top = ""
            document.body.style.left = ""
            document.body.style.right = ""
            document.body.style.width = ""

            window.scrollTo(0, scrollY)
        }
    }, [onClose])

    if (typeof document === "undefined") return null

    return createPortal(
        <div style={styles.modalOverlay} onClick={onClose}>
            <div style={styles.modalCard} onClick={(e) => e.stopPropagation()}>
                <button
                    type="button"
                    style={styles.modalClose}
                    onClick={onClose}
                >
                    ×
                </button>

                <div style={styles.modalTitle}>How to Copy a Review Link</div>

                <div style={styles.modalProgress}>
                    {HELP_STEPS.map((_, i) => (
                        <div key={i} style={styles.modalProgressTrack}>
                            <div
                                style={{
                                    ...styles.modalProgressBar,
                                    background:
                                        i <= step ? "#111111" : "#E6DFE8",
                                    width: i <= step ? "100%" : "0%",
                                }}
                            />
                        </div>
                    ))}
                </div>

                <div key={step} style={styles.modalStepWrap}>
                    <div style={styles.modalStepTitleRow}>
                        <div style={styles.modalStepNumber}>{step + 1}</div>
                        <div style={styles.modalStepTitle}>{title}</div>
                    </div>

                    <div style={styles.modalVisualCard}>
                        <StepVisual visual={visual} />
                    </div>

                    <div style={styles.modalBody}>{body}</div>
                </div>

                <div style={styles.modalFooter}>
                    <button
                        type="button"
                        style={{
                            ...styles.modalBack,
                            opacity: step === 0 ? 0.45 : 1,
                            pointerEvents: step === 0 ? "none" : "auto",
                        }}
                        onClick={onBack}
                    >
                        ← Back
                    </button>

                    <div style={styles.modalCounter}>
                        {step + 1}/{HELP_STEPS.length}
                    </div>

                    <button
                        type="button"
                        style={styles.modalNext}
                        onClick={onNext}
                    >
                        {step === HELP_STEPS.length - 1 ? "Done" : "Next"} →
                    </button>
                </div>
            </div>
        </div>,
        document.body
    )
}
function StepVisual({ visual }: { visual: string }) {
    if (visual === "platforms") {
        return (
            <div style={styles.visualStack}>
                <AnimatedVisualPill
                    left="Google Maps"
                    right="maps.google.com"
                    delay="0ms"
                />
                <AnimatedVisualPill
                    left="Yelp"
                    right="yelp.com"
                    delay="180ms"
                />
                <AnimatedVisualPill
                    left="Trustpilot"
                    right="trustpilot.com"
                    delay="360ms"
                />
            </div>
        )
    }

    if (visual === "search") {
        return (
            <div style={styles.visualStack}>
                <div style={styles.visualSearchAnimated}>
                    <div style={styles.visualSearchLeftWrap}>
                        <div style={styles.visualSearchIcon}>
                            <SearchIcon />
                        </div>

                        <div style={styles.visualTypingLine}>
                            <span style={styles.visualTypingInner}>
                                Your Business Name
                            </span>
                            <span style={styles.visualCaretInline} />
                        </div>
                    </div>
                </div>

                <div style={styles.visualResultCardAnimated}>
                    <div style={styles.visualResultTop}>
                        <div style={styles.visualResultIcon}>
                            <MapPinIcon />
                        </div>

                        <div style={styles.visualResultContent}>
                            <div style={styles.visualResultTitle}>
                                Your Business Name
                            </div>
                            <div style={styles.visualResultSub}>
                                123 Main St. 4.5 ★
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        )
    }

    if (visual === "reviews") {
        return (
            <div style={styles.reviewPanel}>
                <div style={styles.reviewHeader}>Your Business Name</div>

                <div style={styles.reviewTabs}>
                    <span>Overview</span>
                    <span style={styles.reviewTabActiveAnimated}>Reviews</span>
                    <span>Photos</span>
                </div>

                <div style={styles.reviewAreaMock}>
                    <div style={styles.reviewHighlightRing} />
                    <div style={styles.reviewClickHint}>
                        Open the reviews section
                    </div>
                </div>
            </div>
        )
    }

    if (visual === "menu") {
        return (
            <div style={styles.reviewMenuMock}>
                <div style={styles.reviewMiniCardAnimated}>
                    <div style={styles.reviewMiniHeader}>John D. ★</div>
                    <div style={styles.reviewMiniText}>
                        Terrible experience, would not recommend...
                    </div>
                </div>

                <div style={styles.reviewDotsAnimated}>⋮</div>
                <div style={styles.reviewClickNote}>Click the three dots</div>
            </div>
        )
    }

    if (visual === "share") {
        return (
            <div style={styles.shareWrap}>
                <div style={styles.shareReviewCard}>
                    <div style={styles.shareTitle}>John D.</div>
                    <div style={styles.shareText}>Terrible experience...</div>
                </div>

                <div style={styles.shareMenuAnimated}>
                    <div style={styles.shareMenuTop}>Flag as inappropriate</div>
                    <div style={styles.shareMenuBottom}>Share review</div>
                </div>
            </div>
        )
    }

    return (
        <div style={styles.copyCardAnimated}>
            <div style={styles.copyTitle}>Share this review</div>
            <div style={styles.copyUrl}>https://g.co/kgs/abc123...</div>
            <div style={styles.copyButtonAnimated}>Copied!</div>
        </div>
    )
}

function AnimatedVisualPill({
    left,
    right,
    delay,
}: {
    left: string
    right: string
    delay: string
}) {
    return (
        <div
            style={{
                ...styles.visualPill,
                opacity: 0,
                animation: `pillReveal 0.55s cubic-bezier(.22,1,.36,1) forwards`,
                animationDelay: delay,
                position: "relative",
                overflow: "hidden",
            }}
        >
            <div
                style={{
                    position: "absolute",
                    inset: 0,
                    overflow: "hidden",
                    borderRadius: 14,
                    pointerEvents: "none",
                }}
            >
                <div
                    style={{
                        position: "absolute",
                        top: 0,
                        bottom: 0,
                        width: "42%",
                        background:
                            "linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.65), rgba(255,255,255,0))",
                        animation: "shimmerMove 1.8s ease-in-out infinite",
                        animationDelay: delay,
                    }}
                />
            </div>

            <div style={styles.visualPillLeftWrap}>
                <div style={styles.visualPillIcon}>
                    <GlobeIcon />
                </div>
                <div style={styles.visualPillLeft}>{left}</div>
            </div>

            <div style={styles.visualPillRight}>{right}</div>
        </div>
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
    focusedBorder,
}: {
    value: string
    placeholder: string
    icon: React.ReactNode
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void
    hasError?: boolean
    rightAction?: React.ReactNode
    inputMode?: React.HTMLAttributes<HTMLInputElement>["inputMode"]
    focusedBorder?: boolean
}) {
    const [focused, setFocused] = React.useState(false)

    return (
        <div
            style={{
                ...styles.inputWrap,
                border: hasError
                    ? "1px solid #E87878"
                    : focused && focusedBorder
                      ? "2px solid #B54DFF"
                      : "1px solid #D8D2DB",
                boxShadow:
                    focused && focusedBorder
                        ? "0 0 0 3px rgba(181, 77, 255, 0.18)"
                        : "none",
            }}
        >
            <div style={styles.inputIcon}>{icon}</div>
            <input
                value={value}
                onChange={onChange}
                onFocus={() => setFocused(true)}
                onBlur={() => setFocused(false)}
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
                    borderColor: checked ? "#B54DFF" : "#B54DFF",
                }}
            >
                {checked ? <div style={styles.checkboxInner} /> : null}
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
    const digits = value.replace(/\D/g, "")
    return digits.length >= 10
}

function formatPhoneLikeScreenshot(nextValue: string, prevValue: string) {
    const digits = nextValue.replace(/\D/g, "")
    const prevDigits = prevValue.replace(/\D/g, "")

    if (digits.length === 0) return ""

    if (digits.length > 10) {
        return digits
    }

    if (digits.length <= 3) return digits
    if (digits.length <= 6) return `(${digits.slice(0, 3)}) ${digits.slice(3)}`
    if (digits.length <= 10) {
        return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`
    }

    return prevDigits.length > 10 ? digits : nextValue
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
function GlobeIcon() {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            fill="currentColor"
            viewBox="0 0 16 16"
        >
            <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855A8 8 0 0 0 5.145 4H7.5zM4.09 4a9.3 9.3 0 0 1 .64-1.539 7 7 0 0 1 .597-.933A7.03 7.03 0 0 0 2.255 4zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a7 7 0 0 0-.656 2.5zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5zM8.5 5v2.5h2.99a12.5 12.5 0 0 0-.337-2.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5zM5.145 12q.208.58.468 1.068c.552 1.035 1.218 1.65 1.887 1.855V12zm.182 2.472a7 7 0 0 1-.597-.933A9.3 9.3 0 0 1 4.09 12H2.255a7 7 0 0 0 3.072 2.472M3.82 11a13.7 13.7 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5zm6.853 3.472A7 7 0 0 0 13.745 12H11.91a9.3 9.3 0 0 1-.64 1.539 7 7 0 0 1-.597.933M8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855q.26-.487.468-1.068zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.7 13.7 0 0 1-.312 2.5m2.802-3.5a7 7 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7 7 0 0 0-3.072-2.472c.218.284.418.598.597.933M10.855 4a8 8 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4z" />
        </svg>
    )
}

function SearchIcon() {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            fill="none"
            viewBox="0 0 16 16"
        >
            <path
                d="M11.5 11.5L14 14"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
            <circle
                cx="7"
                cy="7"
                r="4.75"
                stroke="currentColor"
                strokeWidth="1.6"
            />
        </svg>
    )
}

function MapPinIcon() {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            fill="none"
            viewBox="0 0 16 16"
        >
            <path
                d="M8 14s4-3.72 4-7A4 4 0 1 0 4 7c0 3.28 4 7 4 7Z"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinejoin="round"
            />
            <circle
                cx="8"
                cy="7"
                r="1.5"
                stroke="currentColor"
                strokeWidth="1.5"
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
function InfoCircleIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <circle
                cx="12"
                cy="12"
                r="8.5"
                stroke="#2E2A2F"
                strokeWidth="1.8"
            />
            <path
                d="M12 10.3v5.2"
                stroke="#2E2A2F"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <circle cx="12" cy="7.2" r="1" fill="#2E2A2F" />
        </svg>
    )
}
function QuestionCircleIcon() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <circle
                cx="12"
                cy="12"
                r="8.5"
                stroke="#B54DFF"
                strokeWidth="1.8"
            />
            <path
                d="M9.9 9.4a2.55 2.55 0 0 1 4.8 1.18c0 1.78-1.9 2.1-2.45 3.3"
                stroke="#B54DFF"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <circle cx="12" cy="17.2" r="1" fill="#B54DFF" />
        </svg>
    )
}
function ShieldIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path
                d="M12 3l7 3v6c0 4.4-2.8 7.7-7 9-4.2-1.3-7-4.6-7-9V6l7-3Z"
                stroke="#111111"
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
                stroke="#111111"
                strokeWidth="1.8"
            />
            <path
                d="M12 7.8v4.7l3 1.8"
                stroke="#111111"
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
                stroke="#111111"
                strokeWidth="1.8"
            />
            <path d="M3 10h18" stroke="#111111" strokeWidth="1.8" />
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
function ArrowRightIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path
                d="M5 12h14M13 6l6 6-6 6"
                stroke="currentColor"
                strokeWidth="1.9"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}
function ArrowLeftIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path
                d="M19 12H5M11 6l-6 6 6 6"
                stroke="currentColor"
                strokeWidth="1.9"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}

const styles: Record<string, React.CSSProperties> = {
    wrapper: {
        width: "100%",
        maxWidth: 720,
        margin: "0 auto",
        padding: "0 14px 40px",
        fontFamily: '"Inter", "Poppins", sans-serif',
        color: "#111111",
    },
    progressWrap: {
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        gap: 18,
        marginBottom: 22,
        flexWrap: "nowrap",
    },

    topStepBtn: {
        border: "none",
        background: "transparent",
        padding: 0,
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        cursor: "pointer",
        minWidth: 82,
    },
    topStepCircle: {
        width: 38,
        height: 38,
        borderRadius: 999,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        fontSize: 16,
        fontWeight: 700,
        transition: "all .25s ease",
    },
    topStepLabel: {
        marginTop: 8,
        fontSize: 14,
        color: "#111111",
        fontWeight: 500,
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    topLine: {
        width: 96,
        height: 2,
        background: "#111111",
        borderRadius: 999,
        transition: "opacity .25s ease",
    },
    card: {
        background: "#FFFFFF",
        border: "1px solid #DDD7DF",
        borderRadius: 16,
        padding: "30px 32px",
        boxSizing: "border-box",
        boxShadow: "0 8px 24px rgba(17,17,17,0.04)",
    },
    stepFade: {
        animation: "fadeSlideIn .22s ease",
    },
    cardTitle: {
        margin: 0,
        textAlign: "center",
        fontSize: 26,
        fontWeight: 600,
        lineHeight: 1.2,
        color: "#111111",
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    cardSub: {
        margin: "8px 0 0",
        textAlign: "center",
        fontSize: 16,
        color: "#6A6470",
        lineHeight: 1.45,
    },
    noticeBox: {
        marginTop: 18,
        borderRadius: 14,
        background: "#F4F1F4",
        border: "1px solid #E2DBE5",
        padding: "14px 16px",
        display: "flex",
        alignItems: "flex-start",
        gap: 12,
    },
    noticeLeftIcon: {
        minWidth: 18,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        marginTop: 1,
    },
    noticeText: {
        color: "#5F5964",
        fontSize: 15,
        lineHeight: 1.45,
    },
    stack16: {
        display: "flex",
        flexDirection: "column",
        gap: 14,
        marginTop: 24,
    },
    stack8: {
        display: "flex",
        flexDirection: "column",
        gap: 8,
    },
    inputWrap: {
        display: "flex",
        alignItems: "center",
        minHeight: 48,
        borderRadius: 14,
        background: "#F9F7F9",
        padding: "0 14px",
        transition: "all .2s ease",
    },
    inputIcon: {
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        marginRight: 12,
        minWidth: 18,
    },
    input: {
        flex: 1,
        height: 46,
        border: "none",
        outline: "none",
        background: "transparent",
        fontSize: 15,
        color: "#111111",
        fontFamily: "inherit",
        minWidth: 0,
    },
    inputRightAction: {
        marginLeft: 8,
    },
    inputActions: {
        display: "flex",
        alignItems: "center",
        gap: 8,
    },
    miniRemove: {
        border: "none",
        background: "#EDE7EF",
        color: "#67606C",
        width: 24,
        height: 24,
        borderRadius: 999,
        cursor: "pointer",
        fontSize: 16,
        lineHeight: 1,
    },
    helpWrap: {
        position: "relative",
        display: "flex",
        alignItems: "center",
    },
    helpIconButton: {
        border: "none",
        background: "transparent",
        width: 24,
        height: 24,
        padding: 0,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        cursor: "pointer",
    },
    tooltip: {
        position: "absolute",
        right: -8,
        bottom: "calc(100% + 10px)",
        width: 220,
        padding: "12px 14px",
        borderRadius: 12,
        background: "#FFFFFF",
        border: "1px solid #DDD7DF",
        boxShadow: "0 16px 30px rgba(17,17,17,0.14)",
        color: "#4F4954",
        fontSize: 14,
        lineHeight: 1.4,
        zIndex: 20,
        animation: "fadeScaleIn .18s ease",
    },
    tooltipArrow: {
        position: "absolute",
        right: 14,
        bottom: -7,
        width: 12,
        height: 12,
        background: "#FFFFFF",
        borderRight: "1px solid #DDD7DF",
        borderBottom: "1px solid #DDD7DF",
        transform: "rotate(45deg)",
    },
    addAnotherButton: {
        margin: "18px auto 0",
        border: "none",
        background: "transparent",
        color: "#6B6570",
        fontSize: 15,
        fontWeight: 500,
        display: "flex",
        alignItems: "center",
        gap: 6,
        cursor: "pointer",
    },
    addPlus: {
        fontSize: 22,
        lineHeight: 1,
    },
    priceNote: {
        fontSize: 16,
        color: "#7A5A32",
    },
    bottomRow: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        marginTop: 42,
        gap: 18,
        flexWrap: "wrap",
    },
    primaryButton: {
        border: "none",
        minWidth: 160,
        height: 42,
        padding: "0 24px",
        borderRadius: 999,
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        gap: 10,
        fontWeight: 500,
        fontSize: 15,
        transition: "all .2s ease",
    },
    primaryButtonEnabled: {
        background: "#9F9F9F",
        color: "#FFFFFF",
        cursor: "pointer",
        boxShadow: "0 10px 20px rgba(0,0,0,0.08)",
    },
    primaryButtonDisabled: {
        background: "#A6A6A6",
        color: "#FFFFFF",
        cursor: "not-allowed",
        opacity: 0.92,
    },
    backButton: {
        border: "none",
        background: "transparent",
        color: "#6B6570",
        display: "inline-flex",
        alignItems: "center",
        fontSize: 15,
        cursor: "pointer",
        padding: 0,
    },
    formStack: {
        marginTop: 22,
        display: "flex",
        flexDirection: "column",
        gap: 12,
    },
    fieldLabel: {
        fontSize: 14,
        color: "#111111",
        fontWeight: 600,
        marginTop: 2,
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    fieldError: {
        color: "#C24B4B",
        fontSize: 13,
        lineHeight: 1.4,
    },
    summaryBox: {
        marginTop: 24,
        background: "#F4F1F4",
        border: "1px solid #DFD9E2",
        borderRadius: 14,
        padding: "20px",
    },
    summaryTop: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 14,
    },
    summaryMuted: {
        color: "#5F5964",
        fontSize: 15,
    },
    summaryPrice: {
        color: "#111111",
        fontSize: 16,
    },
    summaryDivider: {
        height: 1,
        background: "#DED7E2",
        margin: "14px 0 16px",
    },
    summaryBottom: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 14,
    },
    totalLabel: {
        fontSize: 17,
        fontWeight: 500,
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    totalPrice: {
        fontSize: 32,
        fontWeight: 800,
        color: "#D95AF7",
        lineHeight: 1,
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    checkList: {
        marginTop: 24,
        display: "flex",
        flexDirection: "column",
        gap: 14,
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
        width: 0,
        height: 0,
        pointerEvents: "none",
    },
    checkboxCircle: {
        width: 16,
        height: 16,
        minWidth: 16,
        borderRadius: 999,
        border: "1.6px solid #B54DFF",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        marginTop: 2,
        boxSizing: "border-box",
        background: "#FFFFFF",
    },
    checkboxInner: {
        width: 8,
        height: 8,
        borderRadius: 999,
        background: "#B54DFF",
    },
    checkText: {
        fontSize: 15,
        lineHeight: 1.45,
        color: "#5F5964",
    },
    inlineLink: {
        color: "#111111",
        textDecoration: "underline",
    },
    trustPoints: {
        marginTop: 22,
        display: "flex",
        flexDirection: "column",
        gap: 12,
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
        lineHeight: 1.45,
        color: "#2D2930",
    },
    submitError: {
        marginTop: 18,
        padding: "12px 14px",
        borderRadius: 12,
        background: "#FFF3F3",
        border: "1px solid #F0CBCB",
        color: "#B44848",
        fontSize: 14,
    },
    modalOverlay: {
        position: "fixed",
        top: 0,
        right: 0,
        bottom: 0,
        left: 0,
        width: "100vw",
        height: "100dvh",
        minHeight: "100dvh",
        background: "rgba(17,17,17,0.55)",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        padding: 20,
        boxSizing: "border-box",
        zIndex: 999999,
        overscrollBehavior: "contain",
    },
    modalCard: {
        width: "100%",
        maxWidth: 560,
        background: "#F8F5F8",
        borderRadius: 22,
        border: "1px solid #DDD7DF",
        boxShadow: "0 30px 80px rgba(0,0,0,0.28)",
        position: "relative",
        overflow: "hidden",
        animation: "fadeScaleIn .22s ease",
    },
    visualTypingFill: {
        position: "absolute",
        left: 18,
        top: "50%",
        height: 14,
        width: "72%",
        borderRadius: 999,
        background: "rgba(181,77,255,0.14)",
        transform: "translateY(-50%)",
        transformOrigin: "left center",
        animation: "typingBar 1.1s ease forwards",
    },
    modalClose: {
        position: "absolute",
        top: 16,
        right: 16,
        border: "none",
        background: "transparent",
        fontSize: 28,
        lineHeight: 1,
        color: "#6B6570",
        cursor: "pointer",
        zIndex: 2,
    },
    modalTitle: {
        fontSize: 18,
        fontWeight: 500,
        color: "#111111",
        padding: "26px 24px 12px",
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    modalProgress: {
        display: "grid",
        gridTemplateColumns: "repeat(6, 1fr)",
        gap: 6,
        padding: "0 24px 10px",
    },
    modalProgressTrack: {
        height: 4,
        borderRadius: 999,
        background: "#ECE7EF",
        overflow: "hidden",
    },
    modalProgressBar: {
        height: 4,
        borderRadius: 999,
        transition: "all .25s ease",
        transformOrigin: "left center",
    },
    modalStepWrap: {
        padding: "4px 24px 0",
        animation: "fadeSlideIn .22s ease",
    },
    modalStepTitleRow: {
        display: "flex",
        alignItems: "center",
        gap: 12,
        marginBottom: 16,
    },
    modalStepNumber: {
        width: 28,
        height: 28,
        minWidth: 28,
        borderRadius: 999,
        background: "#111111",
        color: "#FFFFFF",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        fontSize: 14,
        fontWeight: 500,
    },
    modalStepTitle: {
        fontSize: 16,
        fontWeight: 500,
        color: "#111111",
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    modalVisualCard: {
        background: "#F4F1F4",
        border: "1px solid #DED8E1",
        borderRadius: 16,
        padding: 18,
        minHeight: 168,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        boxShadow: "inset 0 1px 0 rgba(255,255,255,0.55)",
    },
    modalBody: {
        fontSize: 15,
        lineHeight: 1.5,
        color: "#5F5964",
        padding: "14px 0 20px",
    },
    modalFooter: {
        borderTop: "1px solid #DED8E1",
        padding: "14px 24px",
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 12,
    },
    modalBack: {
        border: "none",
        background: "transparent",
        color: "#6B6570",
        display: "inline-flex",
        alignItems: "center",
        fontSize: 15,
        cursor: "pointer",
        padding: 0,
    },
    modalCount: {
        fontSize: 15,
        color: "#6B6570",
    },
    modalNext: {
        border: "2px solid #B54DFF",
        background: "#111111",
        color: "#FFFFFF",
        height: 42,
        padding: "0 18px",
        borderRadius: 999,
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        gap: 10,
        fontSize: 15,
        fontWeight: 500,
        cursor: "pointer",
        boxShadow: "0 8px 18px rgba(0,0,0,0.16)",
    },
    visualStack: {
        width: "100%",
        display: "flex",
        flexDirection: "column",
        gap: 10,
    },
    visualPill: {
        width: "100%",
        minHeight: 44,
        borderRadius: 14,
        border: "1px solid #DED8E1",
        background: "#FBFAFB",
        padding: "0 14px",
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 12,
    },
    visualPillLeftWrap: {
        display: "flex",
        alignItems: "center",
        gap: 10,
        minWidth: 0,
        position: "relative",
        zIndex: 1,
    },

    visualPillIcon: {
        width: 18,
        height: 18,
        minWidth: 18,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
    },
    visualPillLeft: {
        fontSize: 15,
        color: "#111111",
        fontWeight: 500,
        position: "relative",
        zIndex: 1,
    },
    visualPillRight: {
        fontSize: 12,
        color: "#7D7781",
        position: "relative",
        zIndex: 1,
        fontFamily:
            'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
        letterSpacing: "-0.01em",
    },
    visualSearchAnimated: {
        width: "100%",
        minHeight: 48,
        borderRadius: 999,
        border: "1px solid #DED8E1",
        background: "#FFFFFF",
        display: "flex",
        alignItems: "center",
        padding: "0 18px",
        color: "#6B6570",
        fontSize: 15,
        position: "relative",
        overflow: "hidden",
    },
    visualSearchLeftWrap: {
        display: "flex",
        alignItems: "center",
        gap: 10,
        minWidth: 0,
        flex: 1,
    },

    visualSearchIcon: {
        width: 16,
        height: 16,
        minWidth: 16,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        color: "#6B6570",
        flexShrink: 0,
    },

    visualTypingInner: {
        display: "inline-block",
        overflow: "hidden",
        whiteSpace: "nowrap",
        width: "0ch",
        color: "#6B6570",
        animation: "typingText 1.15s steps(18, end) 0.15s forwards",
    },

    visualResultTop: {
        display: "flex",
        alignItems: "center",
        gap: 10,
    },

    visualResultIcon: {
        width: 18,
        height: 18,
        minWidth: 18,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        color: "#111111",
        flexShrink: 0,
    },

    visualResultContent: {
        minWidth: 0,
    },
    visualTypingLine: {
        display: "inline-flex",
        alignItems: "center",
        minWidth: 0,
        flexShrink: 1,
        whiteSpace: "nowrap",
    },

    visualCaretInline: {
        width: 2,
        height: 18,
        background: "#111111",
        marginLeft: 2,
        flexShrink: 0,
        display: "inline-block",
        animation: "blinkCaret 1s step-end infinite",
    },
    visualTypingFill: {
        position: "absolute",
        left: 18,
        top: "50%",
        height: 14,
        width: "72%",
        borderRadius: 999,
        background: "rgba(181,77,255,0.14)",
        transform: "translateY(-50%)",
        transformOrigin: "left center",
        animation: "typingBar 1.1s ease forwards",
    },
    visualSearchPlaceholder: {
        display: "none",
    },
    visualCaret: {
        width: 2,
        height: 18,
        background: "#111111",
        marginLeft: 10,
        flexShrink: 0,
        position: "relative",
        zIndex: 1,
        animation: "blinkCaret 1s step-end infinite",
    },
    visualResultCardAnimated: {
        width: "100%",
        borderRadius: 14,
        border: "1px solid #DED8E1",
        background: "#FFFFFF",
        padding: "14px 16px",
        opacity: 0,
        animation: "slideInUp .45s ease forwards",
        animationDelay: "700ms",
    },
    visualResultTitle: {
        fontSize: 15,
        fontWeight: 600,
        color: "#111111",
    },
    visualResultSub: {
        marginTop: 4,
        fontSize: 13,
        color: "#7B7580",
    },
    reviewPanel: {
        width: "100%",
        maxWidth: 330,
        borderRadius: 14,
        border: "1px solid #DED8E1",
        background: "#FFFFFF",
        overflow: "hidden",
    },
    reviewHeader: {
        padding: "14px 14px 8px",
        fontSize: 15,
        fontWeight: 600,
        color: "#111111",
    },
    reviewTabs: {
        display: "flex",
        gap: 24,
        padding: "0 14px 0",
        borderBottom: "1px solid #E7E1E8",
        fontSize: 14,
        color: "#7B7580",
        minHeight: 32,
        alignItems: "center",
    },
    reviewTabActiveAnimated: {
        color: "#111111",
        fontWeight: 600,
        borderBottom: "2px solid #111111",
        paddingBottom: 8,
        animation: "tabPulse 1.7s ease-in-out infinite",
    },
    reviewAreaMock: {
        position: "relative",
        minHeight: 82,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        flexDirection: "column",
        gap: 12,
        padding: "16px 14px",
    },
    reviewHighlightRing: {
        width: 124,
        height: 42,
        borderRadius: 999,
        border: "2px solid #B54DFF",
        boxShadow: "0 0 0 8px rgba(181,77,255,0.10)",
        animation: "pulseGlow 1.7s ease-in-out infinite",
    },
    reviewClickHint: {
        fontSize: 12,
        color: "#8A838F",
    },
    reviewMenuMock: {
        width: "100%",
        maxWidth: 360,
        position: "relative",
        minHeight: 116,
    },
    reviewMiniCardAnimated: {
        width: 220,
        borderRadius: 14,
        border: "1px solid #DED8E1",
        background: "#FFFFFF",
        padding: "14px 14px 10px",
        animation: "softFloat 2.4s ease-in-out infinite",
    },
    reviewMiniHeader: {
        fontSize: 14,
        fontWeight: 600,
        color: "#111111",
    },
    reviewMiniText: {
        marginTop: 8,
        fontSize: 13,
        color: "#7B7580",
    },
    reviewDotsAnimated: {
        position: "absolute",
        top: 10,
        right: 34,
        width: 30,
        height: 30,
        borderRadius: 999,
        background: "#F1EDF2",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        color: "#6B6570",
        fontSize: 18,
        lineHeight: 1,
        animation: "pulseGlow 1.8s ease-in-out infinite",
    },
    reviewClickNote: {
        position: "absolute",
        right: 0,
        bottom: 8,
        fontSize: 13,
        color: "#5F5964",
    },
    shareWrap: {
        width: "100%",
        maxWidth: 360,
        display: "flex",
        alignItems: "center",
        gap: 14,
    },
    shareReviewCard: {
        flex: 1,
        minHeight: 76,
        borderRadius: 14,
        border: "1px solid #DED8E1",
        background: "#FFFFFF",
        padding: "14px",
    },
    shareTitle: {
        fontSize: 14,
        fontWeight: 600,
        color: "#111111",
    },
    shareText: {
        marginTop: 8,
        fontSize: 13,
        color: "#B8B2BC",
    },
    shareMenuAnimated: {
        width: 160,
        borderRadius: 12,
        overflow: "hidden",
        boxShadow: "0 10px 24px rgba(17,17,17,0.14)",
        border: "1px solid #DED8E1",
        background: "#FFFFFF",
        opacity: 0,
        animation: "menuDrop .45s ease forwards",
        animationDelay: "350ms",
    },
    shareMenuTop: {
        padding: "12px 14px",
        fontSize: 13,
        color: "#6B6570",
        borderBottom: "1px solid #EFEAF0",
    },
    shareMenuBottom: {
        padding: "12px 14px",
        fontSize: 14,
        color: "#111111",
        fontWeight: 500,
    },
    copyCardAnimated: {
        width: "100%",
        maxWidth: 280,
        borderRadius: 16,
        border: "1px solid #DED8E1",
        background: "#FFFFFF",
        padding: "18px",
        boxShadow: "0 10px 24px rgba(17,17,17,0.08)",
        animation: "fadeScaleIn .35s ease",
    },
    copyTitle: {
        textAlign: "center",
        fontSize: 15,
        fontWeight: 600,
        color: "#111111",
        marginBottom: 14,
        fontFamily: '"Poppins", "Inter", sans-serif',
    },
    copyUrl: {
        minHeight: 36,
        borderRadius: 10,
        background: "#F3F0F4",
        color: "#8A838F",
        display: "flex",
        alignItems: "center",
        padding: "0 12px",
        fontSize: 13,
    },
    copyButtonAnimated: {
        marginTop: 12,
        minHeight: 36,
        borderRadius: 999,
        background: "#111111",
        color: "#FFFFFF",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        fontSize: 14,
        fontWeight: 500,
        animation: "copyFlash .35s ease",
    },
}
