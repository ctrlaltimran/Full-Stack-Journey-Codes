import * as React from "react"
import { addPropertyControls, ControlType, RenderTarget } from "framer"

export default function ReportReviewTimeline(props) {
    const {
        reviewerName,
        reviewText,
        initialRating,
        finalRating,
        ratingLossText,
        trustBefore,
        trustAfter,
        revenueBefore,
        revenueAfter,
        leadsBefore,
        leadsAfter,
        autoPlay,
        animationDuration,
    } = props

    const isCanvas = RenderTarget.current() === RenderTarget.canvas

    const [started, setStarted] = React.useState(autoPlay && !isCanvas)
    const [reportPressed, setReportPressed] = React.useState(false)
    const [phase, setPhase] = React.useState(autoPlay && !isCanvas ? 0 : 0)
    const [displayRating, setDisplayRating] = React.useState(initialRating)

    const timersRef = React.useRef<number[]>([])

    const clearAllTimers = () => {
        timersRef.current.forEach((id) => window.clearTimeout(id))
        timersRef.current = []
    }

    const animateRating = React.useCallback(
        (from: number, to: number, durationMs: number) => {
            const start = performance.now()

            const tick = (now: number) => {
                const progress = Math.min((now - start) / durationMs, 1)
                const eased = 1 - Math.pow(1 - progress, 3)
                const next = from + (to - from) * eased
                setDisplayRating(next)

                if (progress < 1) {
                    requestAnimationFrame(tick)
                } else {
                    setDisplayRating(to)
                }
            }

            requestAnimationFrame(tick)
        },
        []
    )

    const runSequence = React.useCallback(() => {
        clearAllTimers()
        setPhase(0)
        setDisplayRating(initialRating)

        const sequence = [950, 2600, 4700, 7000, 9000].map(
            (time) => time * animationDuration
        )

        timersRef.current.push(
            window.setTimeout(() => setPhase(1), sequence[0]),
            window.setTimeout(() => setPhase(2), sequence[1]),
            window.setTimeout(() => setPhase(3), sequence[2]),
            window.setTimeout(() => {
                setPhase(4)
                animateRating(
                    initialRating,
                    finalRating,
                    2200 * animationDuration
                )
            }, sequence[3]),
            window.setTimeout(() => setPhase(5), sequence[4])
        )
    }, [animationDuration, animateRating, finalRating, initialRating])

    React.useEffect(() => {
        if (!started) return
        runSequence()
        return clearAllTimers
    }, [started, runSequence])

    const handleStart = () => {
        if (reportPressed) return
        setReportPressed(true)
        setStarted(true)
    }

    const replay = () => {
        clearAllTimers()
        setStarted(false)
        setReportPressed(false)
        setPhase(0)
        setDisplayRating(initialRating)

        const id = window.setTimeout(() => {
            setReportPressed(true)
            setStarted(true)
        }, 140)

        timersRef.current.push(id)
    }

    const showTooltip = reportPressed
    const showStep1 = true
    const showStep2 = phase >= 1
    const showStep3 = phase >= 2
    const showStep4 = phase >= 3
    const showStep5 = phase >= 4
    const showStats = phase >= 5

    return (
        <>
            <style>{`
                .rrt-root {
                    width: 100%;
                    box-sizing: border-box;
                    padding: 28px 24px 56px;
                    background: transparent;
                    color: #101828;
                    font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                }

                .rrt-root, .rrt-root * {
                    box-sizing: border-box;
                }

                .rrt-wrap {
                    max-width: 860px;
                    margin: 0 auto;
                }

                .rrt-card {
                    position: relative;
                    border-radius: 26px;
                    border: 1px solid #d7dce3;
                    background: rgba(255,255,255,0.76);
                    backdrop-filter: blur(10px);
                    box-shadow:
                        0 1px 0 rgba(16,24,40,0.02),
                        0 12px 34px rgba(16,24,40,0.04);
                    padding: 28px 28px 24px;
                    overflow: hidden;
                }

                .rrt-cardGlow {
                    pointer-events: none;
                    position: absolute;
                    inset: 0;
                    background:
                        radial-gradient(circle at 15% 0%, rgba(59,130,246,0.06), transparent 28%),
                        radial-gradient(circle at 85% 100%, rgba(239,68,68,0.05), transparent 30%);
                }

                .rrt-row {
                    position: relative;
                    display: grid;
                    grid-template-columns: 48px minmax(0, 1fr);
                    gap: 16px;
                    align-items: start;
                }

                .rrt-row + .rrt-row {
                    margin-top: 18px;
                }

                .rrt-left {
                    position: relative;
                    display: flex;
                    justify-content: center;
                    min-height: 100%;
                }

                .rrt-line {
                    position: absolute;
                    top: 42px;
                    bottom: -20px;
                    width: 2px;
                    border-radius: 999px;
                    transform-origin: top center;
                }

                .rrt-line.blue {
                    background: linear-gradient(180deg, #3767f6 0%, #4f7cff 100%);
                }

                .rrt-line.red {
                    background: linear-gradient(180deg, #ef4444 0%, #dc2626 100%);
                }

                .rrt-row:last-child .rrt-line {
                    display: none;
                }

                .rrt-iconShell {
                    position: relative;
                    z-index: 2;
                    width: 34px;
                    height: 34px;
                    border-radius: 999px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: opacity .45s ease, transform .45s ease, filter .45s ease;
                    box-shadow: 0 10px 18px rgba(17,24,39,0.06);
                }

                .rrt-iconBlue {
                    background: linear-gradient(180deg, #4977ff 0%, #3564f5 100%);
                    color: #ffffff;
                }

                .rrt-iconRed {
                    background: linear-gradient(180deg, #ef4d42 0%, #d93a31 100%);
                    color: #ffffff;
                }

                .rrt-iconHidden {
                    opacity: 0;
                    transform: scale(.72);
                    pointer-events: none;
                    filter: blur(2px);
                }

                .rrt-iconPulse::after {
                    content: "";
                    position: absolute;
                    inset: -6px;
                    border-radius: 999px;
                    border: 1px solid rgba(55,103,246,.20);
                    animation: rrtPulse 2s ease-out infinite;
                }

                .rrt-content {
                    opacity: 0;
                    transform: translateY(18px);
                    filter: blur(5px);
                    transition:
                        opacity .7s cubic-bezier(.22,1,.36,1),
                        transform .7s cubic-bezier(.22,1,.36,1),
                        filter .55s ease;
                }

                .rrt-content.show {
                    opacity: 1;
                    transform: translateY(0);
                    filter: blur(0);
                }

                .rrt-title {
                    margin: 0 0 6px;
                    font-family: Poppins, Inter, sans-serif;
                    font-size: 16px;
                    font-weight: 700;
                    line-height: 1.32;
                    letter-spacing: -.01em;
                    color: #111827;
                }

                .rrt-copy {
                    margin: 0 0 14px;
                    color: #667085;
                    font-size: 14px;
                    line-height: 1.55;
                }

                .rrt-box {
                    border: 1px solid #d6d9e0;
                    background: #fbfbfc;
                    border-radius: 15px;
                    padding: 14px 15px;
                    position: relative;
                    overflow: visible;
                }

                .rrt-reviewTop {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    margin-bottom: 11px;
                }

                .rrt-reviewMeta {
                    display: flex;
                    align-items: center;
                    gap: 11px;
                    min-width: 0;
                }

                .rrt-avatar {
                    width: 28px;
                    height: 28px;
                    border-radius: 50%;
                    background: linear-gradient(180deg, #e7e7e9 0%, #d2d3d8 100%);
                    flex: 0 0 28px;
                    position: relative;
                }

                .rrt-avatar::before {
                    content: "";
                    position: absolute;
                    width: 10px;
                    height: 10px;
                    border-radius: 999px;
                    background: #f4f4f5;
                    left: 9px;
                    top: 5px;
                }

                .rrt-avatar::after {
                    content: "";
                    position: absolute;
                    width: 16px;
                    height: 8px;
                    left: 6px;
                    bottom: 4px;
                    border-radius: 999px 999px 8px 8px;
                    background: #f4f4f5;
                }

                .rrt-reviewNameStars {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    min-width: 0;
                    flex-wrap: wrap;
                }

                .rrt-reviewerName {
                    font-family: Poppins, Inter, sans-serif;
                    font-size: 14px;
                    font-weight: 600;
                    color: #111827;
                    white-space: nowrap;
                }

                .rrt-starsInline {
                    display: inline-flex;
                    align-items: center;
                    gap: 1px;
                    transform: translateY(-.5px);
                }

                .rrt-star {
                    width: 13px;
                    height: 13px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }

                .rrt-star svg {
                    width: 13px;
                    height: 13px;
                    display: block;
                }

                .rrt-reportWrap {
                    position: relative;
                    display: flex;
                    align-items: center;
                    flex-shrink: 0;
                }

                .rrt-reportBtn {
                    appearance: none;
                    height: 30px;
                    padding: 0 12px;
                    border-radius: 12px;
                    border: 1px solid #b9c8ff;
                    background: #edf2ff;
                    color: #3767f6;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-family: Inter, sans-serif;
                    font-size: 12px;
                    font-weight: 500;
                    cursor: pointer;
                    transition:
                        transform .22s ease,
                        background .24s ease,
                        color .24s ease,
                        border-color .24s ease,
                        box-shadow .24s ease;
                    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
                }

                .rrt-reportBtn:hover {
                    transform: translateY(-1px);
                }

                .rrt-reportBtn.active {
                    background: rgba(239,68,68,.10);
                    border-color: rgba(220,58,50,.55);
                    color: #dc3a32;
                    box-shadow:
                        inset 0 1px 0 rgba(255,255,255,.55),
                        0 8px 20px rgba(220,58,50,.10);
                }

                .rrt-reportBtn svg {
                    width: 13px;
                    height: 13px;
                }

                .rrt-tooltip {
                    position: absolute;
                    top: -42px;
                    right: 0;
                    white-space: nowrap;
                    background: #dc3a32;
                    color: #fff;
                    font-size: 11px;
                    font-weight: 600;
                    line-height: 1;
                    padding: 10px 12px;
                    border-radius: 999px;
                    box-shadow: 0 16px 32px rgba(220,58,50,.2);
                    opacity: 0;
                    transform: translateY(8px) scale(.96);
                    transition: opacity .28s ease, transform .28s ease;
                    pointer-events: none;
                }

                .rrt-tooltip.show {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }

                .rrt-tooltip::after {
                    content: "";
                    position: absolute;
                    right: 22px;
                    bottom: -6px;
                    width: 12px;
                    height: 12px;
                    background: #dc3a32;
                    transform: rotate(45deg);
                    border-radius: 2px;
                }

                .rrt-reviewText {
                    margin: 0;
                    font-size: 14px;
                    line-height: 1.56;
                    color: #4b5563;
                }

                .rrt-waitTitle {
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 3px;
                }

                .rrt-ellipsis {
                    display: inline-flex;
                    gap: 3px;
                    margin: 0 1px;
                    transform: translateY(-1px);
                }

                .rrt-ellipsis span {
                    width: 5px;
                    height: 5px;
                    border-radius: 999px;
                    background: #c1c6d0;
                    animation: rrtDotPulse 1.3s ease-in-out infinite;
                }

                .rrt-ellipsis span:nth-child(2) { animation-delay: .12s; }
                .rrt-ellipsis span:nth-child(3) { animation-delay: .24s; }

                .rrt-processing {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .rrt-clockWrap {
                    width: 36px;
                    height: 36px;
                    flex: 0 0 36px;
                    position: relative;
                }

                .rrt-clockFace {
                    width: 36px;
                    height: 36px;
                    border-radius: 999px;
                    border: 3px solid #6f7177;
                    position: relative;
                    background: radial-gradient(circle at 35% 30%, #ffffff 0%, #f5f5f6 70%);
                    box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
                }

                .rrt-clockFace::before {
                    content: "";
                    position: absolute;
                    width: 3px;
                    height: 11px;
                    background: #6f7177;
                    border-radius: 999px;
                    left: 15px;
                    top: 6px;
                    transform-origin: 50% 100%;
                    animation: rrtClockMinute 2s linear infinite;
                }

                .rrt-clockFace::after {
                    content: "";
                    position: absolute;
                    width: 3px;
                    height: 9px;
                    background: #6f7177;
                    border-radius: 999px;
                    left: 15px;
                    top: 15px;
                    transform-origin: 50% 0%;
                    transform: rotate(125deg);
                }

                .rrt-clockCenter {
                    position: absolute;
                    width: 6px;
                    height: 6px;
                    border-radius: 999px;
                    background: #6f7177;
                    left: 15px;
                    top: 15px;
                    z-index: 2;
                }

                .rrt-clockBubble {
                    position: absolute;
                    width: 12px;
                    height: 12px;
                    border-radius: 999px;
                    background: #d3d4d8;
                    top: -3px;
                    right: -2px;
                    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
                }

                .rrt-processingTitle {
                    margin: 0 0 3px;
                    font-family: Poppins, Inter, sans-serif;
                    font-size: 14px;
                    font-weight: 700;
                    color: #111827;
                }

                .rrt-processingMeta {
                    margin: 0;
                    font-size: 12px;
                    color: #6b7280;
                }

                .rrt-statusBar {
                    margin-top: 10px;
                    height: 6px;
                    border-radius: 999px;
                    background: #eceef2;
                    overflow: hidden;
                    position: relative;
                }

                .rrt-statusBar::before {
                    content: "";
                    position: absolute;
                    inset: 0;
                    width: 38%;
                    border-radius: inherit;
                    background: linear-gradient(90deg, #cfd8ff 0%, #7ea3ff 50%, #cfd8ff 100%);
                    background-size: 180% 100%;
                    animation: rrtLoadingSweep 1.7s linear infinite;
                }

                .rrt-rejectBox {
                    background: rgba(255, 245, 245, 0.72);
                    border-color: rgba(239, 68, 68, 0.38);
                }

                .rrt-rejectHead {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                }

                .rrt-rejectTitle {
                    margin: 0 0 4px;
                    font-family: Poppins, Inter, sans-serif;
                    font-size: 14px;
                    font-weight: 700;
                    color: #111827;
                }

                .rrt-rejectText {
                    margin: 0;
                    font-size: 13px;
                    line-height: 1.56;
                    color: #6b7280;
                }

                .rrt-rejectSource {
                    margin-top: 10px;
                    padding-left: 30px;
                    color: #ef4444;
                    font-size: 12px;
                    font-weight: 500;
                }

                .rrt-impactBox {
                    min-height: 146px;
                    background: rgba(255, 246, 246, 0.8);
                    border-color: rgba(239,68,68,.30);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    gap: 8px;
                }

                .rrt-bigRating {
                    margin: 0;
                    font-family: Poppins, Inter, sans-serif;
                    font-size: 56px;
                    line-height: .92;
                    letter-spacing: -.05em;
                    font-weight: 700;
                    color: #dc3a32;
                    font-variant-numeric: tabular-nums;
                }

                .rrt-bigStars {
                    display: inline-flex;
                    align-items: center;
                    gap: 3px;
                }

                .rrt-bigStars .rrt-star svg {
                    width: 24px;
                    height: 24px;
                }

                .rrt-loss {
                    color: #ef4444;
                    font-size: 13px;
                    font-weight: 600;
                }

                .rrt-stats {
                    display: grid;
                    gap: 10px;
                    margin-top: 12px;
                }

                .rrt-statRow {
                    min-height: 42px;
                    border-radius: 12px;
                    border: 1px solid rgba(239,68,68,.24);
                    background: rgba(255,255,255,.76);
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) auto;
                    align-items: center;
                    gap: 12px;
                    padding: 0 14px;
                }

                .rrt-statLabel {
                    font-size: 14px;
                    font-weight: 500;
                    color: #111827;
                }

                .rrt-statNumbers {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    white-space: nowrap;
                    font-size: 14px;
                }

                .rrt-before {
                    color: #6b7280;
                    text-decoration: line-through;
                }

                .rrt-arrow {
                    color: #ef4444;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }

                .rrt-after {
                    color: #ef4444;
                    font-weight: 700;
                }

                .rrt-replay {
                    margin-top: 18px;
                    text-align: center;
                }

                .rrt-replayBtn {
                    appearance: none;
                    background: transparent;
                    border: 0;
                    color: #3767f6;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                }

                @keyframes rrtPulse {
                    0% { opacity: .55; transform: scale(.88); }
                    100% { opacity: 0; transform: scale(1.42); }
                }

                @keyframes rrtDotPulse {
                    0%, 80%, 100% { transform: scale(.7); opacity: .45; }
                    40% { transform: scale(1); opacity: 1; }
                }

                @keyframes rrtClockMinute {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                @keyframes rrtLoadingSweep {
                    0% { transform: translateX(-20%); background-position: 0% 0; }
                    100% { transform: translateX(185%); background-position: 180% 0; }
                }

                @media (max-width: 809px) {
                    .rrt-root {
                        padding: 18px 18px 42px;
                    }

                    .rrt-card {
                        padding: 22px 18px 20px;
                        border-radius: 22px;
                    }

                    .rrt-row {
                        grid-template-columns: 42px minmax(0, 1fr);
                        gap: 12px;
                    }

                    .rrt-title {
                        font-size: 15px;
                    }

                    .rrt-copy,
                    .rrt-reviewText {
                        font-size: 13px;
                    }

                    .rrt-bigRating {
                        font-size: 48px;
                    }

                    .rrt-bigStars .rrt-star svg {
                        width: 22px;
                        height: 22px;
                    }
                }

                @media (max-width: 520px) {
                    .rrt-card {
                        padding: 18px 14px 18px;
                    }

                    .rrt-reviewTop {
                        flex-wrap: wrap;
                        align-items: flex-start;
                    }

                    .rrt-reportWrap {
                        margin-left: auto;
                    }

                    .rrt-tooltip {
                        right: 0;
                        top: -40px;
                        font-size: 10px;
                        padding: 9px 10px;
                    }

                    .rrt-bigRating {
                        font-size: 40px;
                    }

                    .rrt-bigStars .rrt-star svg {
                        width: 20px;
                        height: 20px;
                    }

                    .rrt-statRow {
                        padding: 10px 12px;
                        min-height: 0;
                    }
                }
            `}</style>

            <section className="rrt-root">
                <div className="rrt-wrap">
                    <div className="rrt-card">
                        <div className="rrt-cardGlow" />

                        <TimelineRow
                            icon={
                                <div
                                    className={`rrt-iconShell rrt-iconBlue rrt-iconPulse`}
                                >
                                    <FlagIcon />
                                </div>
                            }
                            lineClass="blue"
                            contentClass="show"
                        >
                            <h3 className="rrt-title">
                                You flag the negative review on Google
                            </h3>
                            <p className="rrt-copy">
                                You click "Report review" and select a policy
                                violation. Google says they'll look into it.
                            </p>

                            <div className="rrt-box">
                                <div className="rrt-reviewTop">
                                    <div className="rrt-reviewMeta">
                                        <div className="rrt-avatar" />
                                        <div className="rrt-reviewNameStars">
                                            <span className="rrt-reviewerName">
                                                {reviewerName}
                                            </span>
                                            <StarRow value={1} size="sm" />
                                        </div>
                                    </div>

                                    <div className="rrt-reportWrap">
                                        <button
                                            className={`rrt-reportBtn ${reportPressed ? "active" : ""}`}
                                            onClick={handleStart}
                                        >
                                            <FlagMiniIcon />
                                            <span>Report</span>
                                        </button>

                                        <div
                                            className={`rrt-tooltip ${showTooltip ? "show" : ""}`}
                                        >
                                            This button almost NEVER works
                                        </div>
                                    </div>
                                </div>

                                <p className="rrt-reviewText">"{reviewText}"</p>
                            </div>
                        </TimelineRow>

                        <TimelineRow
                            hidden={!showStep2}
                            icon={
                                <div
                                    className={`rrt-iconShell rrt-iconBlue ${showStep2 ? "" : "rrt-iconHidden"}`}
                                >
                                    <ClockIconBetter />
                                </div>
                            }
                            lineClass={showStep4 ? "red" : "blue"}
                            contentClass={showStep2 ? "show" : ""}
                        >
                            <h3 className="rrt-title rrt-waitTitle">
                                <span>You wait</span>
                                <span className="rrt-ellipsis">
                                    <span />
                                    <span />
                                    <span />
                                </span>
                                <span>and wait</span>
                                <span className="rrt-ellipsis">
                                    <span />
                                    <span />
                                    <span />
                                </span>
                                <span>and wait</span>
                            </h3>

                            <p className="rrt-copy">
                                Google says the review is "under investigation."
                                Days turn into weeks. No updates.
                            </p>

                            <div className="rrt-box">
                                <div className="rrt-processing">
                                    <div className="rrt-clockWrap">
                                        <div className="rrt-clockFace" />
                                        <div className="rrt-clockCenter" />
                                        <div className="rrt-clockBubble" />
                                    </div>

                                    <div style={{ minWidth: 0 }}>
                                        <p className="rrt-processingTitle">
                                            Review under investigation
                                        </p>
                                        <p className="rrt-processingMeta">
                                            Submitted 3 weeks ago • No response
                                        </p>
                                    </div>
                                </div>
                                <div className="rrt-statusBar" />
                            </div>
                        </TimelineRow>

                        <TimelineRow
                            hidden={!showStep3}
                            icon={
                                <div
                                    className={`rrt-iconShell rrt-iconBlue ${showStep3 ? "" : "rrt-iconHidden"}`}
                                >
                                    <CloseCircleIconBetter />
                                </div>
                            }
                            lineClass={showStep4 ? "red" : "blue"}
                            contentClass={showStep3 ? "show" : ""}
                        >
                            <h3 className="rrt-title">
                                Google decides: "This doesn't violate our
                                policies"
                            </h3>
                            <p className="rrt-copy">
                                After weeks of waiting, Google sends a generic
                                email saying the review will stay up. No
                                explanation.
                            </p>

                            <div className="rrt-box rrt-rejectBox">
                                <div className="rrt-rejectHead">
                                    <RejectShieldIcon />
                                    <div>
                                        <p className="rrt-rejectTitle">
                                            Review does not violate Google's
                                            policies
                                        </p>
                                        <p className="rrt-rejectText">
                                            "We've reviewed the content and
                                            determined it does not violate our
                                            community guidelines. The review
                                            will remain published."
                                        </p>
                                    </div>
                                </div>
                                <div className="rrt-rejectSource">
                                    — Google Support (automated reply)
                                </div>
                            </div>
                        </TimelineRow>

                        <TimelineRow
                            hidden={!showStep4}
                            icon={
                                <div
                                    className={`rrt-iconShell rrt-iconRed ${showStep4 ? "" : "rrt-iconHidden"}`}
                                >
                                    <ChartDownIconBetter />
                                </div>
                            }
                            lineClass="red"
                            contentClass={showStep4 ? "show" : ""}
                        >
                            <h3 className="rrt-title">
                                Your rating drops & customers leave
                            </h3>
                            <p className="rrt-copy">
                                The fake review hurts your star rating.
                                Potential customers see it and choose your
                                competitor instead.
                            </p>

                            <div className="rrt-box rrt-impactBox">
                                <div className="rrt-bigRating">
                                    {displayRating.toFixed(1)}
                                </div>
                                <div className="rrt-bigStars">
                                    <StarRow value={displayRating} size="lg" />
                                </div>
                                <div className="rrt-loss">
                                    ↘ {ratingLossText}
                                </div>
                            </div>
                        </TimelineRow>

                        <TimelineRow
                            hidden={!showStats}
                            icon={
                                <div
                                    className={`rrt-iconShell rrt-iconRed ${showStats ? "" : "rrt-iconHidden"}`}
                                >
                                    <WarningIconBetter />
                                </div>
                            }
                            contentClass={showStats ? "show" : ""}
                        >
                            <h3 className="rrt-title">
                                Your reputation keeps suffering
                            </h3>
                            <p className="rrt-copy">
                                Every day the review stays up, you lose trust,
                                clicks, and revenue. Google won't help.
                            </p>

                            <div className="rrt-stats">
                                <StatRow
                                    label="Trust Score"
                                    before={trustBefore}
                                    after={trustAfter}
                                />
                                <StatRow
                                    label="Company Revenue"
                                    before={revenueBefore}
                                    after={revenueAfter}
                                />
                                <StatRow
                                    label="Monthly Leads"
                                    before={leadsBefore}
                                    after={leadsAfter}
                                />
                            </div>
                        </TimelineRow>

                        <div className="rrt-replay">
                            <button className="rrt-replayBtn" onClick={replay}>
                                ↻ Replay animation
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </>
    )
}

function TimelineRow({
    icon,
    children,
    lineClass,
    contentClass,
    hidden = false,
}: {
    icon: React.ReactNode
    children: React.ReactNode
    lineClass?: "blue" | "red"
    contentClass?: string
    hidden?: boolean
}) {
    return (
        <div
            className="rrt-row"
            style={{
                display: hidden ? "none" : "grid",
            }}
        >
            <div className="rrt-left">
                {lineClass ? <div className={`rrt-line ${lineClass}`} /> : null}
                {icon}
            </div>
            <div className={`rrt-content ${contentClass || ""}`}>
                {children}
            </div>
        </div>
    )
}

function StatRow({
    label,
    before,
    after,
}: {
    label: string
    before: string
    after: string
}) {
    return (
        <div className="rrt-statRow">
            <div className="rrt-statLabel">{label}</div>
            <div className="rrt-statNumbers">
                <span className="rrt-before">{before}</span>
                <span className="rrt-arrow">
                    <ArrowDownMini />
                </span>
                <span className="rrt-after">{after}</span>
            </div>
        </div>
    )
}

function StarRow({
    value,
    size = "sm",
}: {
    value: number
    size?: "sm" | "lg"
}) {
    const full = Math.floor(value)
    const hasHalf = value - full >= 0.5
    const empty = 5 - full - (hasHalf ? 1 : 0)

    const stars: React.ReactNode[] = []

    for (let i = 0; i < full; i++) {
        stars.push(
            <span className="rrt-star" key={`f-${i}`}>
                <StarFilled />
            </span>
        )
    }

    if (hasHalf) {
        stars.push(
            <span className="rrt-star" key="half">
                <StarHalf />
            </span>
        )
    }

    for (let i = 0; i < empty; i++) {
        stars.push(
            <span className="rrt-star" key={`e-${i}`}>
                <StarEmpty />
            </span>
        )
    }

    return (
        <span className={`rrt-starsInline ${size === "lg" ? "lg" : ""}`}>
            {stars}
        </span>
    )
}

function StarFilled() {
    return (
        <svg viewBox="0 0 24 24" fill="none">
            <path
                d="M12 2.9L14.82 8.62L21.13 9.54L16.56 14L17.64 20.28L12 17.31L6.36 20.28L7.44 14L2.87 9.54L9.18 8.62L12 2.9Z"
                fill="#E3B12E"
            />
        </svg>
    )
}

function StarHalf() {
    return (
        <svg viewBox="0 0 24 24" fill="none">
            <path
                d="M12 2.9L14.82 8.62L21.13 9.54L16.56 14L17.64 20.28L12 17.31L6.36 20.28L7.44 14L2.87 9.54L9.18 8.62L12 2.9Z"
                fill="#E5E7EB"
            />
            <path
                d="M12 2.9L14.82 8.62L21.13 9.54L16.56 14L17.64 20.28L12 17.31V2.9Z"
                fill="#E3B12E"
            />
        </svg>
    )
}

function StarEmpty() {
    return (
        <svg viewBox="0 0 24 24" fill="none">
            <path
                d="M12 2.9L14.82 8.62L21.13 9.54L16.56 14L17.64 20.28L12 17.31L6.36 20.28L7.44 14L2.87 9.54L9.18 8.62L12 2.9Z"
                fill="#E5E7EB"
            />
        </svg>
    )
}

function FlagIcon() {
    return (
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <path
                d="M7 20.5V4.8M7 4.8H15.2L14.2 8.2L17.8 11.2H7"
                stroke="currentColor"
                strokeWidth="1.9"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}

function FlagMiniIcon() {
    return (
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
            <path
                d="M7 20.5V4.8M7 4.8H15.2L14.2 8.2L17.8 11.2H7"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}

function ClockIconBetter() {
    return (
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <circle
                cx="12"
                cy="12"
                r="8.2"
                stroke="currentColor"
                strokeWidth="1.8"
            />
            <path
                d="M12 7.8V12L15.1 13.7"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
        </svg>
    )
}

function CloseCircleIconBetter() {
    return (
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <circle
                cx="12"
                cy="12"
                r="8.25"
                stroke="currentColor"
                strokeWidth="1.8"
            />
            <path
                d="M9.3 9.3L14.7 14.7"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <path
                d="M14.7 9.3L9.3 14.7"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
        </svg>
    )
}

function ChartDownIconBetter() {
    return (
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <path
                d="M4 7.8L9.2 13L13.2 9L19.8 15.6"
                stroke="currentColor"
                strokeWidth="1.9"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M15.8 15.6H19.8V11.6"
                stroke="currentColor"
                strokeWidth="1.9"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}

function WarningIconBetter() {
    return (
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <path
                d="M12 4.4L19.4 18H4.6L12 4.4Z"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinejoin="round"
            />
            <path
                d="M12 9.2V13.2"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <circle cx="12" cy="16.2" r="1" fill="currentColor" />
        </svg>
    )
}

function RejectShieldIcon() {
    return (
        <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            style={{ flex: "0 0 18px", marginTop: 1 }}
        >
            <path
                d="M12 3.2L18 5.4V10.5C18 14 15.7 17.1 12 18.5C8.3 17.1 6 14 6 10.5V5.4L12 3.2Z"
                stroke="#EF4444"
                strokeWidth="1.8"
            />
            <path
                d="M9.5 9.5L14.5 14.5"
                stroke="#EF4444"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <path
                d="M14.5 9.5L9.5 14.5"
                stroke="#EF4444"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
        </svg>
    )
}

function ArrowDownMini() {
    return (
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
            <path
                d="M7 10L12 15L17 10"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    )
}

ReportReviewTimeline.defaultProps = {
    reviewerName: "Marcus",
    reviewText:
        "Absolutely terrible experience. The staff was rude and dismissive...",
    initialRating: 4.8,
    finalRating: 4.1,
    ratingLossText: "-0.7 stars lost",
    trustBefore: "92%",
    trustAfter: "61%",
    revenueBefore: "$48K",
    revenueAfter: "$29K",
    leadsBefore: "84",
    leadsAfter: "47",
    autoPlay: true,
    animationDuration: 1,
}

addPropertyControls(ReportReviewTimeline, {
    reviewerName: { type: ControlType.String, title: "Reviewer" },
    reviewText: {
        type: ControlType.String,
        title: "Review",
        displayTextArea: true,
    },
    initialRating: {
        type: ControlType.Number,
        title: "Start ★",
        min: 1,
        max: 5,
        step: 0.1,
    },
    finalRating: {
        type: ControlType.Number,
        title: "End ★",
        min: 1,
        max: 5,
        step: 0.1,
    },
    ratingLossText: { type: ControlType.String, title: "Loss Text" },
    trustBefore: { type: ControlType.String, title: "Trust Before" },
    trustAfter: { type: ControlType.String, title: "Trust After" },
    revenueBefore: { type: ControlType.String, title: "Revenue Before" },
    revenueAfter: { type: ControlType.String, title: "Revenue After" },
    leadsBefore: { type: ControlType.String, title: "Leads Before" },
    leadsAfter: { type: ControlType.String, title: "Leads After" },
    autoPlay: {
        type: ControlType.Boolean,
        title: "Auto Play",
        defaultValue: true,
    },
    animationDuration: {
        type: ControlType.Number,
        title: "Speed",
        min: 0.7,
        max: 2,
        step: 0.1,
        defaultValue: 1,
    },
})
