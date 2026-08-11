import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { deflateSync } from 'zlib';
import {
  Document,
  Packer,
  Paragraph,
  TextRun,
  HeadingLevel,
  ImageRun,
  Table,
  TableRow,
  TableCell,
  WidthType,
  BorderStyle,
  AlignmentType,
} from 'docx';
import sizeOf from 'image-size';
import { unified } from 'unified';
import remarkParse from 'remark-parse';
import remarkGfm from 'remark-gfm';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const mdPath = path.join(__dirname, 'LLD_Industry_Segment_Module.md');
const outPath = path.join(__dirname, 'LLD_Industry_Segment_Module.docx');
const diagramsDir = path.join(__dirname, 'diagrams_industry_segment');

const titles = [
  'Figure 1 — High-level architecture',
  'Figure 2 — Entity relationship diagram',
  'Figure 3 — Denormalized name link to Installed Base',
  'Figure 4 — Create database flow',
  'Figure 5 — Sequence: Create industry segment',
  'Figure 6 — Sequence: Soft-delete and IB impact',
  'Figure 7 — Activity diagram',
  'Figure 8 — Class / module diagram',
];

fs.mkdirSync(diagramsDir, { recursive: true });

function toPakoUrl(source) {
  const state = JSON.stringify({ code: source, mermaid: { theme: 'default' } });
  const compressed = deflateSync(Buffer.from(state, 'utf8'), { level: 9 });
  const encoded = compressed.toString('base64').replace(/\+/g, '-').replace(/\//g, '_');
  return `https://mermaid.ink/img/pako:${encoded}`;
}

const markdown = fs.readFileSync(mdPath, 'utf8');
const mermaidBlocks = [...markdown.matchAll(/```mermaid\r?\n([\s\S]*?)```/g)].map((m) => m[1].trim());

for (let i = 0; i < mermaidBlocks.length; i += 1) {
  const n = i + 1;
  const mmdPath = path.join(diagramsDir, `diagram_${n}.mmd`);
  const pngPath = path.join(diagramsDir, `diagram_${n}.png`);
  fs.writeFileSync(mmdPath, mermaidBlocks[i] + '\n');
  console.log('Rendering diagram', n);
  const res = await fetch(toPakoUrl(mermaidBlocks[i]));
  const buf = Buffer.from(await res.arrayBuffer());
  const ctype = res.headers.get('content-type') || '';
  if (!res.ok || !ctype.includes('image')) {
    console.error('Failed diagram', n, res.status, buf.slice(0, 120).toString());
    continue;
  }
  fs.writeFileSync(pngPath, buf);
  console.log('Wrote', pngPath, buf.length);
}

function scaleDims(width, height, maxW = 540, maxH = 680) {
  const ratio = Math.min(maxW / width, maxH / height, 1);
  return {
    width: Math.max(1, Math.round(width * ratio)),
    height: Math.max(1, Math.round(height * ratio)),
  };
}

function diagramParagraphs(index) {
  const imgPath = path.join(diagramsDir, `diagram_${index}.png`);
  const title = titles[index - 1] || `Figure ${index}`;
  if (!fs.existsSync(imgPath)) {
    return [new Paragraph({ children: [new TextRun({ text: `[Missing diagram ${index}]`, italics: true })] })];
  }
  const buf = fs.readFileSync(imgPath);
  const isJpeg = buf[0] === 0xff && buf[1] === 0xd8;
  const dim = sizeOf(buf);
  const scaled = scaleDims(dim.width || 800, dim.height || 600);
  return [
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 200, after: 100 },
      children: [
        new ImageRun({
          type: isJpeg ? 'jpg' : 'png',
          data: buf,
          transformation: { width: scaled.width, height: scaled.height },
          altText: { title, description: title, name: title },
        }),
      ],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 300 },
      children: [new TextRun({ text: title, italics: true, size: 18 })],
    }),
  ];
}

function inlineRuns(node, marks = {}) {
  if (!node) return [];
  if (node.type === 'text') return [new TextRun({ text: node.value || '', ...marks })];
  if (node.type === 'strong') return (node.children || []).flatMap((c) => inlineRuns(c, { ...marks, bold: true }));
  if (node.type === 'emphasis') return (node.children || []).flatMap((c) => inlineRuns(c, { ...marks, italics: true }));
  if (node.type === 'inlineCode') return [new TextRun({ text: node.value || '', font: 'Consolas', size: 18, ...marks })];
  if (node.type === 'link') {
    const label = (node.children || []).map((c) => c.value || '').join('') || node.url;
    return [new TextRun({ text: label, color: '0563C1', underline: {}, ...marks })];
  }
  if (node.type === 'break') return [new TextRun({ break: 1 })];
  if (node.children) return node.children.flatMap((c) => inlineRuns(c, marks));
  return [];
}

function cellText(node) {
  if (!node) return '';
  if (node.type === 'text') return node.value || '';
  if (node.type === 'inlineCode') return node.value || '';
  if (node.children) return node.children.map(cellText).join('');
  return '';
}

function mdTableToDocx(tableNode) {
  const rows = [];
  for (const row of tableNode.children || []) {
    if (row.type !== 'tableRow') continue;
    const isHeader = rows.length === 0;
    const cells = (row.children || [])
      .filter((c) => c.type === 'tableCell')
      .map(
        (cell) =>
          new TableCell({
            width: { size: 2000, type: WidthType.DXA },
            borders: {
              top: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
              bottom: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
              left: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
              right: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
            },
            children: [
              new Paragraph({
                children: [new TextRun({ text: cellText(cell), bold: isHeader, size: 18 })],
              }),
            ],
          })
      );
    if (cells.length) rows.push(new TableRow({ children: cells }));
  }
  if (!rows.length) return [];
  return [new Table({ width: { size: 100, type: WidthType.PERCENTAGE }, rows }), new Paragraph({ children: [] })];
}

function headingLevel(depth) {
  if (depth === 1) return HeadingLevel.HEADING_1;
  if (depth === 2) return HeadingLevel.HEADING_2;
  if (depth === 3) return HeadingLevel.HEADING_3;
  return HeadingLevel.HEADING_4;
}

let diagramIndex = 0;

function blockToChildren(node) {
  switch (node.type) {
    case 'heading': {
      const text = (node.children || []).map(cellText).join('');
      // Force bold + new page for main LLD sections 1–18.
      const sectionMatch = (node.depth || 1) === 2 ? text.match(/^(\d{1,2})\.\s/) : null;
      const sectionNum = sectionMatch ? Number(sectionMatch[1]) : 0;
      const isNumberedSection = sectionNum >= 1 && sectionNum <= 18;
      return [
        new Paragraph({
          heading: headingLevel(node.depth || 1),
          pageBreakBefore: isNumberedSection,
          spacing: { before: 240, after: 120 },
          children: [new TextRun({ text, bold: isNumberedSection || (node.depth || 1) === 1 })],
        }),
      ];
    }
    case 'paragraph': {
      const runs = (node.children || []).flatMap((c) => inlineRuns(c));
      return [new Paragraph({ spacing: { after: 120 }, children: runs.length ? runs : [new TextRun('')] })];
    }
    case 'blockquote': {
      const texts = [];
      for (const child of node.children || []) {
        if (child.type === 'paragraph') texts.push((child.children || []).map(cellText).join(''));
      }
      return [
        new Paragraph({
          spacing: { after: 120 },
          indent: { left: 360 },
          children: [new TextRun({ text: texts.join(' '), italics: true, color: '555555' })],
        }),
      ];
    }
    case 'code': {
      if (node.lang === 'mermaid') {
        diagramIndex += 1;
        return diagramParagraphs(diagramIndex);
      }
      return String(node.value || '')
        .split(/\r?\n/)
        .map(
          (line) =>
            new Paragraph({
              spacing: { after: 0 },
              children: [new TextRun({ text: line || ' ', font: 'Consolas', size: 16 })],
            })
        );
    }
    case 'list': {
      const items = [];
      (node.children || []).forEach((item, i) => {
        const prefix = node.ordered ? `${(node.start || 1) + i}. ` : 'â€¢ ';
        const parts = [];
        for (const child of item.children || []) {
          if (child.type === 'paragraph') parts.push(...(child.children || []).flatMap((c) => inlineRuns(c)));
        }
        items.push(
          new Paragraph({
            spacing: { after: 60 },
            indent: { left: 360 },
            children: [new TextRun(prefix), ...(parts.length ? parts : [new TextRun('')])],
          })
        );
      });
      return items;
    }
    case 'table':
      return mdTableToDocx(node);
    case 'thematicBreak':
      return [
        new Paragraph({
          spacing: { before: 120, after: 120 },
          children: [new TextRun({ text: 'â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€' })],
        }),
      ];
    case 'html':
      return [];
    default:
      return node.children ? node.children.flatMap(blockToChildren) : [];
  }
}

const tree = unified().use(remarkParse).use(remarkGfm).parse(markdown);
const children = (tree.children || []).flatMap(blockToChildren);

const doc = new Document({
  creator: 'Complaint Management',
  title: 'LLD — Industry Segment Module',
  description: 'Low-Level Design for Industry Segment Module',
  sections: [
    {
      properties: { page: { margin: { top: 720, right: 720, bottom: 720, left: 720 } } },
      children,
    },
  ],
});

const buffer = await Packer.toBuffer(doc);
fs.writeFileSync(outPath, buffer);
console.log('Wrote', outPath, `(${buffer.length} bytes)`, `diagrams embedded: ${diagramIndex}`);



