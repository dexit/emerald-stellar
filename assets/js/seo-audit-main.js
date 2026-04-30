/**
 * Main SEO Audit JavaScript
 * 
 * Implements the "Main Approach" using Chrome's Built-in AI (Gemini Nano)
 * and structured results inspired by superExampleSEO.
 */

(function ($) {
  "use strict";

  class SEOAuditMain {
    constructor() {
      this.init();
    }

    init() {
      // Listen for the audit trigger (could be from Elementor or Admin UI)
      $(document).on("seo:audit:start", (e, data) => {
        this.runAudit(data);
      });
    }

    /**
     * Run the full audit process
     */
    async runAudit(data) {
      const { content, title, url } = data;

      Swal.fire({
        title: "Starting SEO Audit",
        html: `<div class="seo-audit-steps">
          <div class="step" id="step-traditional">✓ Traditional Analysis</div>
          <div class="step" id="step-ai">⌛ AI Analysis (Local Gemini Nano)</div>
        </div>`,
        showConfirmButton: false,
        didOpen: async () => {
          try {
            // 1. Run traditional audit via AJAX
            const traditionalResults = await this.runTraditionalAudit(content, title, url);
            $("#step-traditional").text("✓ Traditional Analysis Complete");

            // 2. Run AI Analysis using local model
            if (this.isAIAvailable()) {
              const aiResults = await this.runLocalAIAnalysis(content, traditionalResults);
              $("#step-ai").text("✓ AI Analysis Complete");
              this.showFinalReport(traditionalResults, aiResults);
            } else {
              $("#step-ai").text("⚠ AI Unavailable - Showing Traditional Results");
              this.showFinalReport(traditionalResults, null);
            }
          } catch (error) {
            Swal.fire("Error", error.message, "error");
          }
        }
      });
    }

    /**
     * Call the backend for traditional checks
     */
    runTraditionalAudit(content, title, url) {
      return new Promise((resolve, reject) => {
        $.ajax({
          url: seoAuditSettings.apiUrl,
          type: "POST",
          data: {
            action: "seo_audit_run_full",
            nonce: seoAuditSettings.nonce,
            content: content,
            title: title,
            url: url
          },
          success: (response) => {
            if (response.success) resolve(response.data);
            else reject(new Error(response.data.message));
          },
          error: () => reject(new Error("Network error during audit"))
        });
      });
    }

    /**
     * Run local AI analysis using Chrome's Gemini Nano
     */
    async runLocalAIAnalysis(content, traditionalResults) {
      try {
        const session = await window.ai.languageModel.create({
          systemPrompt: seoAuditSettings.systemPrompt
        });

        const prompt = `Analyze this content for SEO.
        Content: ${content.substring(0, 5000)}
        Existing Analysis Findings: ${JSON.stringify(traditionalResults.seo)}
        
        Provide additional insights for H1 tags, keyword consistency, and readability following the structured JSON output.`;

        const response = await session.prompt(prompt);
        
        // Attempt to parse the JSON response from AI
        try {
          // Find JSON block in response if AI wrap it in markdown
          const jsonMatch = response.match(/\{[\s\S]*\}/);
          return jsonMatch ? JSON.parse(jsonMatch[0]) : { error: "Failed to parse AI response" };
        } catch (e) {
          console.error("AI JSON Parse Error:", e);
          return { ai_raw_response: response };
        }
      } catch (error) {
        console.error("Local AI Error:", error);
        throw new Error("Local AI failed: " + error.message);
      }
    }

    /**
     * Check if Chrome's built-in AI is available
     */
    isAIAvailable() {
      return typeof window.ai !== "undefined" && typeof window.ai.languageModel !== "undefined";
    }

    /**
     * Show the final combined report
     */
    showFinalReport(traditional, ai) {
      const results = { 
        seo: { ...traditional.seo, ...(ai?.seo || {}) },
        performance: { ...traditional.performance, ...(ai?.performance || {}) },
        ui: { ...traditional.ui, ...(ai?.ui || {}) },
        technology: { ...traditional.technology, ...(ai?.technology || {}) },
        links: { ...traditional.links, ...(ai?.links || {}) }
      };
      
      let html = `<div class="seo-final-report">`;

      // 1. PERFORMANCE SUMMARY
      const ui = traditional.ui || {};
      if (ui.pageInsights && !ui.pageInsights.error) {
        const ps = ui.pageInsights;
        const metrics = ps.metrics || {};
        const scores = ps.scores || {};
        
        let diagnosticsHtml = '';
        if (ps.diagnostics && ps.diagnostics.length > 0) {
           diagnosticsHtml = `<div class="diagnostics-list" style="margin-top: 20px; text-align: left; font-size: 13px; max-height: 200px; overflow-y: auto; background: #fff8e1; border-left: 4px solid #ffc107; padding: 10px;">
             <strong>⚠️ Top Diagnostics:</strong>
             <ul style="margin: 5px 0 0 15px; padding: 0;">
               ${ps.diagnostics.slice(0, 5).map(d => `<li style="margin-bottom: 5px;"><strong>${d.title}</strong>: ${d.description}</li>`).join('')}
             </ul>
           </div>`;
        }

        html += `
          <div class="report-section performance-summary">
            <h3>Automated Lighthouse Scores</h3>
            <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px;">
              <div class="metric-card ${scores.performance >= 90 ? 'passed' : scores.performance >= 50 ? 'warning' : 'failed'}">
                <span class="m-label">Performance</span><span class="m-value">${Math.round(scores.performance)}</span>
              </div>
              <div class="metric-card ${scores.accessibility >= 90 ? 'passed' : scores.accessibility >= 50 ? 'warning' : 'failed'}">
                <span class="m-label">Accessibility</span><span class="m-value">${Math.round(scores.accessibility)}</span>
              </div>
              <div class="metric-card ${scores.best_practices >= 90 ? 'passed' : scores.best_practices >= 50 ? 'warning' : 'failed'}">
                <span class="m-label">Best Practices</span><span class="m-value">${Math.round(scores.best_practices)}</span>
              </div>
              <div class="metric-card ${scores.seo >= 90 ? 'passed' : scores.seo >= 50 ? 'warning' : 'failed'}">
                <span class="m-label">SEO</span><span class="m-value">${Math.round(scores.seo)}</span>
              </div>
            </div>
            <h4>Core Web Vitals</h4>
            <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;">
              <div class="metric-card"><span class="m-label">FCP</span><span class="m-value" style="font-size:14px">${metrics.fcp}</span></div>
              <div class="metric-card"><span class="m-label">LCP</span><span class="m-value" style="font-size:14px">${metrics.lcp}</span></div>
              <div class="metric-card"><span class="m-label">TBT</span><span class="m-value" style="font-size:14px">${metrics.tbt}</span></div>
              <div class="metric-card"><span class="m-label">CLS</span><span class="m-value" style="font-size:14px">${metrics.cls}</span></div>
              <div class="metric-card"><span class="m-label">Speed Index</span><span class="m-value" style="font-size:14px">${metrics.si}</span></div>
            </div>
            ${ps.crux_real_world ? `
              <h4 style="margin-top:15px">CrUX Field Data (Real Chrome Users)</h4>
              <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                <div class="metric-card ${ps.crux_real_world.fcp === 'FAST' ? 'passed' : ps.crux_real_world.fcp === 'AVERAGE' ? 'warning' : 'failed'}"><span class="m-label">FCP Rating</span><span class="m-value" style="font-size:11px">${ps.crux_real_world.fcp}</span></div>
                <div class="metric-card ${ps.crux_real_world.lcp === 'FAST' ? 'passed' : ps.crux_real_world.lcp === 'AVERAGE' ? 'warning' : 'failed'}"><span class="m-label">LCP Rating</span><span class="m-value" style="font-size:11px">${ps.crux_real_world.lcp}</span></div>
                <div class="metric-card ${ps.crux_real_world.cls === 'FAST' ? 'passed' : ps.crux_real_world.cls === 'AVERAGE' ? 'warning' : 'failed'}"><span class="m-label">CLS Rating</span><span class="m-value" style="font-size:11px">${ps.crux_real_world.cls}</span></div>
              </div>
            ` : ''}
            ${diagnosticsHtml}
          </div>
        `;
      }
      
      // 2. CATEGORY BREAKDOWN
      const categories = [
        { id: 'seo', label: 'On-Page SEO & Content' },
        { id: 'links', label: 'Links & Backlinks' },
        { id: 'ui', label: 'UI, Mobile & Social' },
        { id: 'technology', label: 'Scripts & Tracking' },
        { id: 'performance', label: 'Technical SEO' }
      ];

      categories.forEach(cat => {
        const items = results[cat.id];
        if (!items || Object.keys(items).length === 0) return;

        html += `
          <div class="report-section">
            <h4 class="section-title">${cat.label}</h4>
            <div class="factors-grid">
              ${Object.entries(items).map(([key, val]) => {
                if (!val || typeof val !== 'object' || key === 'pageInsights') return '';
                const statusClass = val.passed ? "passed" : "failed";
                return `
                  <div class="report-item ${statusClass}">
                    <div class="item-header">
                      <span class="badge ${statusClass}">${key.replace(/([A-Z])/g, ' $1')}</span>
                      <strong>${val.shortAnswer || (val.passed ? 'Passed' : 'Action Needed')}</strong>
                    </div>
                    <div class="item-details">
                      <p>${val.answer || ""}</p>
                      ${val.recommendation ? `<div class="rec">💡 ${val.recommendation}</div>` : ""}
                    </div>
                  </div>
                `;
              }).join('')}
            </div>
          </div>
        `;
      });
      
      html += `</div>`;

      Swal.fire({
        title: "AI-Powered Full SEO Audit (50+ Factors)",
        html: html,
        width: 1000,
        showCloseButton: true,
        confirmButtonText: "Close Audit Report"
      });
    }
  }

  // Initialize
  $(document).ready(() => {
    window.seoAudit = new SEOAuditMain();
    
    // Bridge for Elementor trigger
    $(window).on("elementor/frontend/init", function () {
      if (typeof elementor !== 'undefined' && elementor.channels) {
        elementor.channels.editor.on("seo:audit:analyze", function () {
          // Robust content extraction for Elementor
          let allContent = "";
          if (elementor.getFrontend && elementor.getFrontend().getElementsRawData) {
             const rawData = elementor.getFrontend().getElementsRawData();
             allContent = JSON.stringify(rawData);
          } else {
             allContent = $(".elementor-inner").html() || $("body").html();
          }
          
          $(document).trigger("seo:audit:start", {
            content: allContent,
            title: document.title,
            url: window.location.href,
            meta_description: $('meta[name="description"]').attr('content') || ""
          });
        });
      }
    });
  });

})(jQuery);
