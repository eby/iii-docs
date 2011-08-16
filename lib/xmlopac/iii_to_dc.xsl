<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
	xmlns:srw_dc="info:srw/schema/1/dc-schema"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:zs="http://www.loc.gov/zing/srw/"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<xsl:output method="xml" indent="yes" encoding="UTF-8"/>
<xsl:template match="/WXROOT">
<zs:searchRetrieveResponse>
	<zs:version>1.1</zs:version>
	<zs:numberOfRecords><xsl:value-of select="PAGEINFO/ENTRYCOUNT" /></zs:numberOfRecords>
	<zs:records>
	<xsl:for-each select="Heading/Title">
		<zs:record>
		<zs:recordSchema>info:srw/schema/1/dc-v1.1</zs:recordSchema>
		<zs:recordPacking>xml</zs:recordPacking>
		<zs:recordData>
		<srw_dc:dc xmlns:srw_dc="info:srw/schema/1/dc-schema" xsi:schemaLocation="info:srw/schema/1/dc-schema http://www.loc.gov/z3950/agency/zing/srw/dc-schema.xsd">
			
			<!-- type (1 of 2) -->
			<type>
				<xsl:for-each select="IIIRECORD/TYPEINFO/BIBLIOGRAPHIC/FIXFLD">			
					<xsl:choose>		
						<xsl:when test="FIXLABEL = 'BIB LVL'">
							<xsl:if test="FIXVALUE ='c'">
								<xsl:text>collection</xsl:text>
							</xsl:if>
						</xsl:when>
						<xsl:when test="FIXLABEL = 'MAT TYPE'">
							<xsl:if test="FIXVALUE='d' or FIXVALUE='f' or FIXVALUE='p' or FIXVALUE='t'">
								<xsl:text>manuscript</xsl:text>
							</xsl:if>
							<xsl:choose>
								<xsl:when test="FIXVALUE='a' or FIXVALUE='t'">text</xsl:when>
								<xsl:when test="FIXVALUE='e' or FIXVALUE='f'">cartographic</xsl:when>
								<xsl:when test="FIXVALUE='c' or FIXVALUE='d'">notated music</xsl:when>
								<xsl:when test="FIXVALUE='i' or FIXVALUE='j'">sound recording</xsl:when>
								<xsl:when test="FIXVALUE='k'">still image</xsl:when>
								<xsl:when test="FIXVALUE='g'">moving image</xsl:when>
								<xsl:when test="FIXVALUE='r'">three dimensional object</xsl:when>
								<xsl:when test="FIXVALUE='m'">software, multimedia</xsl:when>
								<xsl:when test="FIXVALUE='p'">mixed material</xsl:when>
							</xsl:choose>
						</xsl:when>
					</xsl:choose>
				</xsl:for-each>
			</type>
			
			<!-- language -->
			<xsl:for-each select="IIIRECORD/TYPEINFO/BIBLIOGRAPHIC/FIXFLD">
				<xsl:choose>
					<xsl:when test="FIXLABEL = 'LANG'">
						<language>
							<xsl:value-of select="FIXVALUE" />
						</language>
					</xsl:when>
				</xsl:choose>
			</xsl:for-each>
			
			<xsl:for-each select="IIIRECORD/VARFLDPRIMARYALTERNATEPAIR/VARFLDPRIMARY/VARFLD">
				<xsl:choose>
				
					<!-- title -->
					<xsl:when test="MARCINFO/MARCTAG = '245'">
						<title>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:value-of select="SUBFIELDDATA" />
							</xsl:for-each>
						</title>
					</xsl:when>
					
					<!-- creator -->
					<xsl:when test="MARCINFO/MARCTAG = '100' or 
									MARCINFO/MARCTAG = '110' or 
									MARCINFO/MARCTAG = '111' or 
									MARCINFO/MARCTAG = '700' or 
									MARCINFO/MARCTAG = '710' or 
									MARCINFO/MARCTAG = '711' or 
									MARCINFO/MARCTAG = '720'">
						
						<xsl:for-each select="MARCSUBFLD">
							<xsl:if test="SUBFIELDINDICATOR = 'a'">
								<creator>
									<xsl:value-of select="SUBFIELDDATA" />
								</creator>
							</xsl:if>
						</xsl:for-each>
					</xsl:when>
	
					<!-- type (2 of 2) -->
					<xsl:when test="MARCINFO/MARCTAG = '655'">
						<type>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:value-of select="SUBFIELDDATA" />
							</xsl:for-each>
						</type>
					</xsl:when>
					
					<!-- publisher -->
					<!-- date -->
					<xsl:when test="MARCINFO/MARCTAG = '260'">
						<publisher>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'a' or SUBFIELDINDICATOR = 'b'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</publisher>
						<date>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'c'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>						
						</date>
					</xsl:when>
					
					<!-- format -->
					<xsl:when test="MARCINFO/MARCTAG = '856'">
						<format>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'q'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</format>
					</xsl:when>
					
					<!-- description -->
					<xsl:when test="MARCINFO/MARCTAG = '520' or MARCINFO/MARCTAG = '521'">
						<description>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'a'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</description>
						<!-- 
						<xsl:for-each select="marc:datafield[500&lt;@tag][@tag&lt;=599][not(@tag=506 or @tag=530 or @tag=540 or @tag=546)]">
							<description>
								<xsl:value-of select="marc:subfield[@code='a']"/>
							</description>
						</xsl:for-each>
						-->
					</xsl:when>	
					
					<!-- subject -->
					<xsl:when test="MARCINFO/MARCTAG = '600' or 
									MARCINFO/MARCTAG = '610' or 
									MARCINFO/MARCTAG = '611' or 
									MARCINFO/MARCTAG = '630' or 
									MARCINFO/MARCTAG = '650' or
									MARCINFO/MARCTAG = '653'">
						<subject>
						<!--	<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'a' or
											  SUBFIELDINDICATOR = 'b' or
											  SUBFIELDINDICATOR = 'c' or
											  SUBFIELDINDICATOR = 'd' or
											  SUBFIELDINDICATOR = 'q'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>-->
							<xsl:value-of select="DisplayForm" />
						</subject>
					</xsl:when>	
					
					<!-- coverage -->
					<xsl:when test="MARCINFO/MARCTAG = '752'">
						<coverage>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'a' or
											  SUBFIELDINDICATOR = 'b' or
											  SUBFIELDINDICATOR = 'c' or
											  SUBFIELDINDICATOR = 'd'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</coverage>
					</xsl:when>	
					
					<!-- relation -->
					<xsl:when test="MARCINFO/MARCTAG = '530'">
						<relation>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'a' or
											  SUBFIELDINDICATOR = 'b' or
											  SUBFIELDINDICATOR = 'c' or
											  SUBFIELDINDICATOR = 'd' or
											  SUBFIELDINDICATOR = 'u'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</relation>
					</xsl:when>	
					<xsl:when test="MARCINFO/MARCTAG = '760' or 
									MARCINFO/MARCTAG = '762' or 
									MARCINFO/MARCTAG = '765' or 
									MARCINFO/MARCTAG = '767' or 
									MARCINFO/MARCTAG = '770' or
									MARCINFO/MARCTAG = '772' or
									MARCINFO/MARCTAG = '773' or
									MARCINFO/MARCTAG = '774' or
									MARCINFO/MARCTAG = '775' or
									MARCINFO/MARCTAG = '776' or
									MARCINFO/MARCTAG = '777' or
									MARCINFO/MARCTAG = '780' or
									MARCINFO/MARCTAG = '785' or
									MARCINFO/MARCTAG = '786' or
									MARCINFO/MARCTAG = '787'">
						<relation>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'o' or
											  SUBFIELDINDICATOR = 't'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</relation>
					</xsl:when>	
					
					<!-- identifier -->
					<xsl:when test="MARCINFO/MARCTAG = '856'">
						<identifier>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'u'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</identifier>
					</xsl:when>
					<xsl:when test="MARCINFO/MARCTAG = '020'">
						<identifier>
							<xsl:text>URN:ISBN:</xsl:text>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'a'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</identifier>
					</xsl:when>
					
					<!-- rights -->
					<xsl:when test="MARCINFO/MARCTAG = '506' or MARCINFO/MARCTAG = '540'">
						<rights>
							<xsl:for-each select="MARCSUBFLD">
								<xsl:if test="SUBFIELDINDICATOR = 'a'">
									<xsl:value-of select="SUBFIELDDATA" />
								</xsl:if>
							</xsl:for-each>
						</rights>
					</xsl:when>				
				</xsl:choose>
			</xsl:for-each>
			</srw_dc:dc>
		</zs:recordData>
		<zs:recordPosition><xsl:value-of select="TitleSeq"/></zs:recordPosition>
		</zs:record>
	</xsl:for-each>
	</zs:records>
	</zs:searchRetrieveResponse>
</xsl:template>
</xsl:stylesheet>
